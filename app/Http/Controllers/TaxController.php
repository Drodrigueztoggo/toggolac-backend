<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxCategorie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TaxController extends Controller
{
    /** US country_id in the countries table */
    private const COUNTRY_US       = 233;
    /** Colombia country_id */
    private const COUNTRY_COLOMBIA = 48;
    /** Free-shipping threshold in USD */
    private const FREE_SHIPPING_THRESHOLD = 75;

    public function calculateTaxes(Request $request)
    {
        try {
            $category_id            = $request->category_id;
            $destination_country_id = (int) $request->destination_country_id;
            $productoPrecio         = (float) $request->producto_precio;
            $manualTax              = $request->manual_tax;
            $weight                 = (float) ($request->weight ?? 0);
            // Total order value (used for the free-shipping threshold).
            // Falls back to the single-product price when not provided.
            $totalOrderPrice        = (float) ($request->total_order_price ?? $productoPrecio);
            $destinationPostalCode  = $request->destination_postal_code ?? null;

            $data = TaxCategorie::with('infoTax')->whereHas('infoTax', function ($q) use ($destination_country_id) {
                $q->where('destination_country_id', $destination_country_id);
            })
                ->whereRaw("JSON_CONTAINS(category_ids, '$category_id')")
                ->select('id', 'tax_id', 'category_id', 'category_ids')->get();

            $taxes = $data->map(function ($item) {
                return $item['infoTax'];
            });

            $calculatedTaxes = [];
            foreach ($taxes as $tax) {
                $taxValue = $this->calculateTax($tax, $productoPrecio, $taxes);
                $calculatedTaxes[] = [
                    'id'        => $tax->id,
                    'code'      => $tax->code,
                    'is_manual' => $tax->is_manual,
                    'name'      => $tax->name,
                    'amount'    => $tax->value,
                    'value'     => $taxValue,
                ];
            }

            if (isset($manualTax) && count($manualTax) > 0) {
                foreach ($manualTax as $manual) {
                    $calculatedTaxes = collect($calculatedTaxes)->map(function ($item) use ($manual) {
                        if ($item['id'] == $manual['id'] && $item['is_manual'] == 1) {
                            $item['value'] = $manual['value'];
                        }
                        return $item;
                    })->all();
                }
            }

            // ── Free-shipping: zero out SGR/RCG database taxes when order >= $75 ──
            // These taxes are always included in $totalValue; zeroing them ensures
            // the grand total actually reflects free shipping.
            $isFreeShipping = $totalOrderPrice >= self::FREE_SHIPPING_THRESHOLD;
            if ($isFreeShipping) {
                $calculatedTaxes = array_map(function ($tax) {
                    if (in_array($tax['code'], ['SGR', 'RCG'])) {
                        $tax['value'] = 0;
                    }
                    return $tax;
                }, $calculatedTaxes);
            }

            $totalValue = array_sum(array_column($calculatedTaxes, 'value'));

            // ── Compute actual shipping charge (not in $calculatedTaxes) ─────────
            $shippingAmount = 0;
            if (!$isFreeShipping) {
                if ($destination_country_id === self::COUNTRY_COLOMBIA) {
                    $ratePerLb      = (float) config('services.shippo.colombia_rate_per_lb', 4.35);
                    $shippingAmount = round($weight * $ratePerLb, 2);
                } elseif ($destination_country_id === self::COUNTRY_US) {
                    $shippingAmount = $this->getShippoEstimate($weight, $destinationPostalCode);
                }
            }
            $totalValue += $shippingAmount;

            $response = [
                'taxes' => [
                    $this->ServiceCost($calculatedTaxes),
                    $this->ShippingCost($shippingAmount),
                    $this->TaxCost($calculatedTaxes, $destination_country_id, $productoPrecio),
                    $this->IvaCost($calculatedTaxes),
                    $this->ServiceDuty($calculatedTaxes),
                ],
                'total_taxes' => $totalValue,
                'sub_total'   => $productoPrecio,
                'total'       => $totalValue + $productoPrecio,
            ];

            return $response;
        } catch (\Exception $e) {
            dd($e);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateTax($tax, $productoPrecio, $allTaxes)
    {
        $taxValue = 0;
        if (!is_null($tax->tax_dependency_id)) {
            $dependency = $allTaxes->firstWhere('id', $tax->tax_dependency_id);
            if ($dependency) {
                $dependencyValue = $this->calculateTax($dependency, $productoPrecio, $allTaxes);
                $productoPrecio += $dependencyValue;
            }
        }

        if ($tax->if_price !== null) {
            $operator        = $tax->if_price;
            $conditionalValue = (float) $tax->value_conditional;

            if ($operator === '<'  && $productoPrecio <  $conditionalValue) {
                $taxValue = $this->calculateTaxValue($tax, $productoPrecio);
            } elseif ($operator === '<=' && $productoPrecio <= $conditionalValue) {
                $taxValue = $this->calculateTaxValue($tax, $productoPrecio);
            } elseif ($operator === '>'  && $productoPrecio >  $conditionalValue) {
                $taxValue = $this->calculateTaxValue($tax, $productoPrecio);
            } elseif ($operator === '>=' && $productoPrecio >= $conditionalValue) {
                $taxValue = $this->calculateTaxValue($tax, $productoPrecio);
            }
        } else {
            $taxValue = $this->calculateTaxValue($tax, $productoPrecio);
        }

        return $taxValue;
    }

    private function calculateTaxValue($tax, $productoPrecio)
    {
        if ($tax->type === 'percentage') {
            return ($productoPrecio * $tax->value) / 100;
        } elseif ($tax->type === 'total') {
            return $tax->value;
        }
        return 0;
    }

    /**
     * Shipping cost display line.
     * The actual amount has already been computed in calculateTaxes() and
     * incorporates the free-shipping rule + Colombia/US logic.
     */
    private function ShippingCost(float $amount): array
    {
        return [
            'code'   => 'COST',
            'name'   => 'COSTO DE ENVÍO',
            'tach'   => $amount === 0.0,
            'amount' => $amount,
        ];
    }

    private function TaxCost($taxes, $destination_country_id, $productoPrecio): array
    {
        try {
            $isColombiaDestination = ($destination_country_id == self::COUNTRY_COLOMBIA);
            $totalTax = $isColombiaDestination ? 0 : round($productoPrecio * 0.07, 2);

            return [
                'code'   => 'TAX',
                'name'   => 'TAXES EN USA',
                'tach'   => false,
                'amount' => $totalTax,
            ];
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    private function IvaCost($taxes): array
    {
        try {
            $totalTax = 0;
            foreach ($taxes as $tax) {
                if ($tax['code'] === 'IVA') {
                    $totalTax += $tax['value'];
                }
            }
            return ['code' => 'IVA', 'name' => 'IVA COLOMBIA', 'tach' => false, 'amount' => $totalTax];
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    private function ServiceCost($taxes): array
    {
        try {
            $totalTax = 0;
            foreach ($taxes as $tax) {
                if ($tax['code'] === 'UTL') {
                    $totalTax += $tax['value'];
                }
            }
            return ['code' => 'SRV', 'name' => 'COSTO SERVICIO', 'tach' => false, 'amount' => $totalTax];
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    private function ServiceDuty($taxes): array
    {
        try {
            $totalTax = 0;
            foreach ($taxes as $tax) {
                if ($tax['code'] === 'ARL') {
                    $totalTax += $tax['value'];
                }
            }
            return ['code' => 'ARL', 'name' => 'ARANCELES', 'tach' => false, 'amount' => $totalTax];
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    /**
     * Calls the Shippo Rates API and returns the cheapest available rate in USD.
     * Falls back to SHIPPO_FALLBACK_RATE when the API is unavailable or returns no rates.
     */
    private function getShippoEstimate(float $weightLbs, ?string $destinationZip): float
    {
        $fallback = (float) config('services.shippo.fallback_rate', 25);
        $token    = config('services.shippo.token');

        if (!$token) {
            Log::warning('Shippo API token not configured — using fallback rate.');
            return $fallback;
        }

        if (!$destinationZip) {
            Log::info('Shippo: no destination ZIP provided — using fallback rate.');
            return $fallback;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => 'ShippoToken ' . $token])
                ->post('https://api.goshippo.com/shipments/', [
                    'address_from' => [
                        'name'    => config('services.shippo.origin_name',   'Toggolac'),
                        'street1' => config('services.shippo.origin_street', '100 S Biscayne Blvd'),
                        'city'    => config('services.shippo.origin_city',   'Miami'),
                        'state'   => config('services.shippo.origin_state',  'FL'),
                        'zip'     => config('services.shippo.origin_zip',    '33131'),
                        'country' => 'US',
                    ],
                    'address_to' => [
                        'zip'     => $destinationZip,
                        'country' => 'US',
                    ],
                    'parcels' => [[
                        'weight'        => max(0.1, $weightLbs),
                        'mass_unit'     => 'lb',
                        'length'        => '12',
                        'width'         => '10',
                        'height'        => '6',
                        'distance_unit' => 'in',
                    ]],
                    'async' => false,
                ]);

            if (!$response->successful()) {
                Log::error('Shippo API error: ' . $response->status() . ' ' . $response->body());
                return $fallback;
            }

            $rates = collect($response->json('rates', []))
                ->filter(fn($r) => isset($r['amount']) && floatval($r['amount']) > 0)
                ->sortBy(fn($r) => floatval($r['amount']));

            if ($rates->isNotEmpty()) {
                return round(floatval($rates->first()['amount']), 2);
            }

            Log::info('Shippo returned no rates for ZIP ' . $destinationZip . ' — using fallback.');
        } catch (\Throwable $e) {
            Log::error('Shippo exception: ' . $e->getMessage());
        }

        return $fallback;
    }
}
