<?php

use App\Events\OrderCreated;
use App\Http\Controllers\AppServiceResourcesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityPersonalShopperController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\CarrierShippingRateController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\CountrieController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DlocalPaymentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\MallController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\ShoppingCartController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\StoreMallController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WordpressServiceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\PersonalShopperController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\ReceptionCenterController;
use App\Http\Controllers\ReturnsController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TransactionController;
use Stichoza\GoogleTranslate\GoogleTranslate;

// Route::post('/login', [AuthController::class, 'login']);



Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login');
    Route::post('register', 'register');
    Route::post('logout', 'logout');
    Route::post('refresh', 'refresh');
});


// Route::middleware(['auth'])->group(function () {
//     Route::controller(AuthController::class)->group(function () {
//         Route::post('refresh', 'refresh');
//     });
// });




Route::group(['prefix' => 'category'], function () {
    Route::controller(CategoryController::class)->group(function () {
        Route::get('get_list', 'getCategoryList');
        Route::get('get', 'getCategory');
        Route::middleware(['checkAuth'])->group(function () {
            Route::post('add', 'addCategory');
            Route::post('update', 'updateCategory');
            Route::delete('delete/{id}', 'deleteCategory');
        });
    });
});

Route::group(['prefix' => 'brand'], function () {
    Route::controller(BrandController::class)->group(function () {
        Route::get('get_list_public', 'getBrandsListPublic');
        Route::get('get_list', 'getBrandsList');
        Route::get('excel', 'downloadBrandExcel');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getBrands');
            Route::post('add', 'addBrand');
            Route::post('update', 'updateBrand');
            Route::delete('delete/{id}', 'deleteBrand');
            Route::post('show', 'showBrand');
        });
    });
});


Route::group(['prefix' => 'malls'], function () {
    Route::controller(MallController::class)->group(function () {
        Route::get('get_list', 'getMallList');
        Route::get('get_list_public', 'getMallListPublic');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getMalls');
            Route::post('add', 'addMall');
            Route::post('update', 'updateMall');
            Route::delete('delete/{id}', 'deleteMall');
            Route::post('show', 'showMall');
        });
    });
});


Route::group(['prefix' => 'storemalls'], function () {
    Route::controller(StoreMallController::class)->group(function () {
        Route::get('get_list', 'getStoreMallList');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getStoreMall');
            Route::post('add', 'addStoreMall');
            Route::post('update', 'updateStoreMall');
            Route::delete('delete/{id}', 'deleteStoreMall');
            Route::post('show', 'showStoreMall');
        });
    });
});

Route::group(['prefix' => 'countrie'], function () {
    Route::controller(CountrieController::class)->group(function () {
        Route::get('get', 'getCountrie');
    });
});

Route::group(['prefix' => 'state'], function () {
    Route::controller(StateController::class)->group(function () {
        Route::get('get_state_country', 'getStateCountry');
    });
});


Route::group(['prefix' => 'city'], function () {
    Route::controller(CityController::class)->group(function () {
        Route::get('get_city_state', 'getCityState');
    });
});


Route::group(['prefix' => 'user'], function () {
    Route::controller(UserController::class)->group(function () {
        Route::get('get_list', 'getUsersList');
        Route::get('activate/{token}', 'activateAccount');
        Route::post('add', 'addUser');
        Route::post('reset_password_user', 'resetPasswordAndEmail');
        Route::get('excel-shopper', 'downloadPersonalShopperExcel');
        Route::middleware(['checkAuth'])->group(function () {
            Route::post('update', 'updateUser');
            Route::post('update_bank_accounts/{id}', 'updateBankAccounts');
            Route::get('get', 'getUsers');
            Route::get('excel', 'downloadUsersExcel');
            Route::delete('delete/{id}', 'deleteUser');
            Route::post('show', 'showUser');
        });
    });
});




Route::group(['prefix' => 'products'], function () {
    Route::controller(ProductController::class)->group(function () {
        Route::get('download-products-excel', 'downloadProductsExcel');

        Route::get('get_list', 'getProductsList');
        Route::get('get_list_public', 'getProductsListPublic');
        Route::get('product-detail/{id}', 'showProductPublic');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getProducts');
            Route::post('add', 'addProduct');
            Route::post('update', 'updateProduct');
            Route::delete('delete/{id}', 'deleteProduct');
            Route::post('show', 'showProduct');
        });
    });
});



Route::group(['prefix' => 'offers'], function () {
    Route::controller(OfferController::class)->group(function () {
        Route::get('get_list', 'getOfferList');
        Route::get('get_list_public', 'getOfferListPublic');
        Route::get('offer-detail/{id}', 'getOfferDetail');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getOffers');
            Route::post('add', 'addOffer');
            Route::post('update', 'updateOffer');
            Route::delete('delete/{id}', 'deleteOffer');
            Route::post('show', 'showOffer');
        });
    });
});


Route::group(['prefix' => 'personal_shopper'], function () {
    Route::controller(AvailabilityPersonalShopperController::class)->group(function () {
        Route::post('create_availability', 'create');
        Route::post('get_availability_full_day', 'getFullDayAvailability');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get_availability', 'getDispoPersonalShopper');
        });
    });
});

Route::group(['prefix' => 'quote'], function () {
    Route::controller(QuoteController::class)->group(function () {
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getQuotes');
            Route::get('get_quote_status', 'getQuoteStatus');
            Route::post('create', 'create');
            Route::post('update/{id}', 'updateStatus');
            Route::post('update_date_time/{id}', 'updateDateTime');
        });
    });
});

Route::group(['prefix' => 'carriers'], function () {
    Route::controller(CarrierController::class)->group(function () {
        Route::get('get_list', 'getCarrierList');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getCarriers');
            Route::post('add', 'addCarrier');
            Route::post('update', 'updateCarrier');
            Route::delete('delete/{id}', 'deleteCarrier');
            Route::post('show', 'showCarrier');
        });
    });
});


Route::group(['prefix' => 'carriers_shipping_rate'], function () {
    Route::controller(CarrierShippingRateController::class)->group(function () {
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getCarrierShippingRates');
            Route::post('add', 'addCarrierShippingRate');
            Route::post('update', 'updateCarrierShippingRate');
            Route::delete('delete/{id}', 'deleteCarrierShippingRate');
            Route::post('show', 'showCarrierShippingRate');
        });
    });
});


Route::group(['prefix' => 'shipping'], function () {
    Route::controller(ShipmentController::class)->group(function () {

        Route::get('download-shipping-excel', 'downloadShipmentExcel');
        Route::post('download-shipping-pdf-thank', 'downloadShipmentPdfThank');

        Route::post('test-email/{order_id}', 'sendEmailShipmentOrder');



        Route::post('calculate', 'calculateShippingCost');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getShipment');
            Route::post('show', 'showInfoShipment');
            Route::post('add_shipment', 'addShipment');
            Route::post('update_shipment', 'updateShipment');
        });
    });
});

Route::group(['prefix' => 'wp_service'], function () {
    Route::controller(WordpressServiceController::class)->group(function () {
        Route::get('get_offers', 'getWordPressData');
        Route::get('get_last_products', 'getLastProducts');
        Route::get('get_last_products_paginate', 'getLastProductsPaginate');
        Route::get('get_all_categories', 'getAllCategories');
    });
});


Route::group(['prefix' => 'cart_user'], function () {
    Route::controller(ShoppingCartController::class)->group(function () {

        Route::middleware(['checkAuth'])->group(function () {
            Route::get('see_user_cart_admin', 'seeUserCartAdmin');
            Route::get('see_user_cart', 'seeUserCart');
            Route::post('add_products', 'addProductsToCart');
            Route::post('remove_form_cart', 'removeFromCart');
            Route::post('update_quantity', 'updateQuantity');
        });
    });
});


Route::group(['prefix' => 'purchase_order'], function () {
    Route::controller(PurchaseOrderController::class)->group(function () {
        Route::get('download-purchase-excel', 'downloadPurchaseExcel');
        Route::get('download-purchase-excel-sales-ok', 'downloadPurchaseExcelSalesOk');
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getPurchaseOrder');
            Route::get('show/{id}', 'showPurchaseOrder');
            Route::post('add', 'addPurchaseOrder');
            Route::put('update', 'updatePurchaseOrder');
            Route::delete('delete/{id}', 'deletePurchaseOrder');
        });
    });
});

Route::group(['prefix' => 'help_center'], function () {
    Route::controller(HelpCenterController::class)->group(function () {
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getHelpCenter');
            Route::get('show/{id}', 'showHelpCenter');
            Route::post('add', 'addHelpCenter');
            Route::post('send_comment_help_center', 'sendCommentHelpCenter');
            Route::put('update/{id}', 'updateHelpCenter');
            Route::delete('delete/{id}', 'deleteHelpCenter');
            Route::delete('delete_image/{id}', 'deleteHelpCenterImage');
        });
    });
});

Route::group(['prefix' => 'personal_shopper'], function () {
    Route::controller(PersonalShopperController::class)->group(function () {
        Route::middleware(['checkAuth'])->group(function () {
            Route::get('get', 'getPersonalShopper');
            Route::get('show/{id}', 'showPersonalShopper');
            Route::post('add', 'addPersonalShopper');
            Route::put('update', 'updatePersonalShopper');
            Route::delete('delete/{id}', 'deletePersonalShopper');
        });
    });
});

Route::group(['prefix' => 'reception_center'], function () {
    Route::controller(ReceptionCenterController::class)->group(function () {

        Route::post('product_orders', 'productOrders');
        Route::get('get', 'getReceptionCenter');
        Route::middleware(['checkAuth'])->group(function () {

            Route::post('save_review', 'saveReview');
        });
    });
});

Route::group(['prefix' => 'return_purchase'], function () {
    Route::controller(ReturnsController::class)->group(function () {

        Route::get('get', 'getReturnProduct');
        Route::post('return_product', 'saveReturnProduct');
        Route::post('update_status_return_product', 'updateStatusReturnProduct');
        Route::middleware(['checkAuth'])->group(function () {
            Route::post('send_comment_return', 'sendCommentReturn');
        });
    });
});
Route::group(['prefix' => 'purchase_invoince'], function () {
    Route::controller(PurchaseInvoiceController::class)->group(function () {

        Route::middleware(['checkAuth'])->group(function () {
            Route::post('add_invoice', 'addPurchaseInvoice');
        });
    });
});


Route::group(['prefix' => 'd-local'], function () {
    Route::controller(DlocalPaymentController::class)->group(function () {
        Route::get('back-url', function () {
            return "back payment";
        });
        Route::get('success-url', function () {
            return "success payment";
        });
        Route::post('notification-url/{order_id}', 'notificationPayment');
        Route::post('send-email/{order_id}', 'sendEmailConfirm');

        Route::middleware(['checkAuth'])->group(function () {

            Route::post('get', 'readPayment');
            Route::post('read-payment', 'readPayment');
            Route::post('make-payment', 'makePayment');
        });
    });
});


Route::group(['prefix' => 'commisions'], function () {
    Route::controller(CommissionController::class)->group(function () {

        Route::get('get', 'getCommisions');

        // Route::middleware(['checkAuth'])->group(function () {

        // });
    });
});

Route::group(['prefix' => 'app-services'], function () {
    Route::controller(AppServiceResourcesController::class)->group(function () {
        Route::get('search', 'searchApp');
    });
});

Route::group(['prefix' => 'transactions'], function () {
    Route::controller(TransactionController::class)->group(function () {
        Route::get('get-transactions', 'getTransactions');
        Route::post('export', 'downloadTransactionsExcel');
    });
});


Route::group(['prefix' => 'taxes'], function () {
    Route::controller(TaxController::class)->group(function () {
        Route::post('calculate', 'calculateTaxes');
    });
});

Route::group(['prefix' => 'prueba'], function () {
    Route::controller(CurrencyController::class)->group(function () {
        Route::get('update', 'updateCurrencies');
    });
});

Route::group(['prefix' => 'evaluations'], function () {
    Route::controller(EvaluationController::class)->group(function () {
        Route::middleware(['checkAuth'])->group(function () {
            Route::post('add', 'addEvaluation');
        });
    });
});
