<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class CategoryController extends Controller
{


    public function getCategoryList(Request $request)
    {
        try {
            $TGGlanguage = $request->TGGlanguage;
            $translate = new GoogleTranslateController();


            $category = Category::select('id', 'name_category as name', 'image_category')->get();

            $categoryFormat = $category->map(function ($item) use ($translate, $TGGlanguage) {
                return [
                    "id" => $item['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($item['name'], $TGGlanguage) : $item['name'],
                    "image" => $item['image']
                ];
            });


            return response()->json([
                'data' => $categoryFormat
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function getCategory(Request $request)
    {

        try {

            $TGGlanguage = $request->TGGlanguage;

            $perPage = $request->query('per_page', 20);
            $filter_name = $request->query('name');


            $category = Category::select('id', 'name_category as name', 'description_category as description', 'image_category');

            if (isset($filter_name)) {
                $category =   $category->where('name_category', 'like', "%$filter_name%"); // Aplica el filtro si se proporciona el nombre
            }

            $category = $category->paginate($perPage);

            $translate = new GoogleTranslateController();



            $categoryFormat = $category->map(function ($item) use ($translate, $TGGlanguage) {
                return [
                    "id" => $item['id'],
                    "name" => $TGGlanguage != 'es' ? $translate->translateText($item['name'], $TGGlanguage) : $item['name'],
                    "description" => $TGGlanguage != 'es' ? $translate->translateText($item['description'], $TGGlanguage) : $item['description'],
                    "image" => $item['image']
                ];
            });

            $data = [
                "data" => $categoryFormat,
                'current_page' => $category->currentPage(),
                'first_page_url' => $category->url(1),
                'from' => $category->firstItem(),
                'last_page' => $category->lastPage(),
                'last_page_url' => $category->url($category->lastPage()),
                'next_page_url' => $category->nextPageUrl(),
                'path' => $category->url($category->currentPage()),
                'per_page' => $category->perPage(),
                'prev_page_url' => $category->previousPageUrl(),
                'to' => $category->lastItem(),
                'total' => $category->total(),
            ];


            return response()->json([
                'data' => $data,
                'status' => 'success',
                'message' => 'Category list'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function addCategory(AddCategoryRequest $request)
    {
        try {
            // Buscar si ya existe una categoría eliminada con el mismo nombre
            $existingCategory = Category::withTrashed()
                ->where('name_category', $request->name_category)
                ->whereNotNull('deleted_at') // Solo categorías eliminadas
                ->first();

            if ($existingCategory) {
                // Restaurar la categoría y actualizar los datos
                $existingCategory->name_category = $request->name_category;
                $existingCategory->description_category = $request->description_category;
                $existingCategory->image_category = $this->storeImage($request->file('image_category'));
                $existingCategory->restore(); // Restaurar la categoría

                return response()->json([
                    'status' => 'success',
                    'message' => 'Category restored and updated successfully',
                    'user' => $existingCategory
                ]);
            }

            // Si no existe, crear una nueva categoría
            $date = now();
            $imagePath = $this->storeImage($request->file('image_category'));

            $category = Category::create([
                'name_category' => $request->name_category,
                'description_category' => $request->description_category,
                'image_category' => $imagePath,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'user' => $category
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCategory(UpdateCategoryRequest $request)
    {
        try {
            $name = $request->name_category;
            $categoryId = $request->id;


            // Buscar si existe una categoría eliminada con el mismo nombre
            $existingCategory = Category::withTrashed()
                ->where('name_category', $name)
                ->whereNotNull('deleted_at') // Solo categorías eliminadas
                ->first();


            if ($existingCategory) {
                // Actualizar la información de la categoría eliminada
                $existingCategory->name_category = $name;
                $existingCategory->description_category = $request->description_category;
                $existingCategory->image_category = $this->storeImage($request->file('image_category'));
                $existingCategory->restore(); // Restaurar la categoría

                $this->deleteCategory($categoryId); // se elimina la categoria actual??

                return response()->json([
                    'status' => 'success',
                    'message' => 'Category restored and updated successfully',
                    'user' => $existingCategory
                ]);
            } else {
                // Si no existe una categoría eliminada con el mismo nombre, actualizar una categoría existente
                $category = Category::find($categoryId);

                if (!$category) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Category not found'
                    ], 404);
                }

                // Actualizar la categoría existente
                $category->name_category = $name;
                $category->description_category = $request->description_category;

                if ($request->hasFile('image_category')) {
                    $this->deleteImage($category->image_category);
                    $imagePath = $this->storeImage($request->file('image_category'));
                    $category->image_category = $imagePath;
                }

                $category->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Category updated successfully',
                    'user' => $category
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function deleteCategory($id)
    {
        try {

            $category = Category::findOrFail($id);
            // $this->deleteImage($category->image_category); // confirmar si se debe eliminar la imagen tambien


            $category->delete();

            return [
                'status' => 'success',
                'message' => 'Elimination is confirmed'
            ];
        } catch (Exception $e) {

            if (!isset($category)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El id ' . $id . ' No se encuentra registrado.'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function storeImage($image)
    {
        try {
            $imageName = uniqid() . '_' . now()->format('YmdHis') . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('uploads/category/' . now()->format('Y-m-d'), $imageName, 'public');
            return $imagePath;
        } catch (Exception $e) {
            throw new Exception('Error al guardar la imagen: ' . $e->getMessage());
        }
    }


    private function deleteImage($imagePath)
    {
        try {
            if ($imagePath && Storage::exists('public/' . $imagePath)) {
                Storage::delete('public/' . $imagePath);
            }
        } catch (Exception $e) {
            throw new Exception('Error al eliminar la imagen: ' . $e->getMessage());
        }
    }
}
