<?php

namespace aleafoodapi\Http\Controllers;

use aleafoodapi\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

class RecipeController extends Controller
{
    public function index()
    {
        $recipes = Recipe::all();
        return $recipes;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:30|unique:recipes',
            'description' => 'nullable|string|max:255',
            'preparation_time' => 'required|integer|max:5000',
            'cooking_time' => 'required|integer|max:5000',
            'ingredients' => 'required|array',
            'ingredients.*' => 'required|integer|exists:ingredients,id',
        ]);

        $user = Auth::user();

        $recipe = new Recipe;
        $recipe->name = $request->name;
        $recipe->description = $request->description;
        $recipe->preparation_time = $request->preparation_time;
        $recipe->cooking_time = $request->cooking_time;
        $recipe->user_id = $user->id;
        $recipe->save();
        $recipe->ingredients()->sync($request->ingredients);
        $recipe = $recipe->fresh();

        return $recipe;
    }

    public function delete($id)
    {

        $recipe = Recipe::find($id);

        if (!$recipe) {
            return response()->json(['error' => 'Recipe not found'], 404);
        }

        if ($recipe->thumbnail) {
            Storage::delete($recipe->thumbnail);
            Storage::delete(str_replace("thumbnail", "banner", $recipe->thumbnail));
            Storage::delete(str_replace("thumbnail", "largeBanner", $recipe->thumbnail));
            $recipe->thumbnail = null;
        }

        $recipe->delete();

        return response()->json(['message' => 'Recipe deleted']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => [
                'required','string','min:2','max:30',
                Rule::unique('recipes')->ignore($id),
            ],
            'description' => 'nullable|string|max:255',
            'preparation_time' => 'required|integer|max:5000',
            'cooking_time' => 'required|integer|max:5000',
            'ingredients' => 'required|array',
            'ingredients.*' => 'required|integer|exists:ingredients,id',
        ]);

        $recipe = Recipe::find($id);

        if (!$recipe) {
            return response()->json(['error' => 'Recipe not found'], 404);
        }

        $recipe->description = $request->description;
        $recipe->preparation_time = $request->preparation_time;
        $recipe->cooking_time = $request->cooking_time;
        if ($request->name != $recipe->name){
            $recipe->slug = null;
            $recipe->update([
                'name' => $request->name,
            ]);
        }else {
            $recipe->save();
        }
        $recipe->ingredients()->sync($request->ingredients);


        $recipe = $recipe->fresh();

        return $recipe;
    }

    public function show($id)
    {
        $recipes = Recipe::find($id);

        if (!$recipes) {
            return response()->json(['error' => 'Recipe not found'], 404);
        }

        return $recipes;
    }


    /**
     * add or change the thumbnail
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateThumbnail(Request $request, $id)
    {
        $request->validate([
            'thumbnail' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $recipe = Recipe::find($id);

        if (!$recipe) {
            return response()->json(['error' => 'Recipe not found'], 404);
        }

        if ($recipe->thumbnail) {
            Storage::delete($recipe->thumbnail);
            Storage::delete(str_replace("thumbnail", "banner", $recipe->thumbnail));
            Storage::delete(str_replace("thumbnail", "largeBanner", $recipe->thumbnail));
            $recipe->thumbnail = null;
        }
        $thumbnailName = Str::slug($recipe->name).'_thumbnail'.time().'.'.request()->thumbnail->getClientOriginalExtension();
        $bannerName = Str::slug($recipe->name).'_banner'.time().'.'.request()->thumbnail->getClientOriginalExtension();
        $bannerLargeName = Str::slug($recipe->name).'_largeBanner'.time().'.'.request()->thumbnail->getClientOriginalExtension();

        $thumbnailPath = $request->thumbnail->storeAs('thumbnails',$thumbnailName);
        $bannerPath = $request->thumbnail->storeAs('banners',$bannerName);
        $bannerLargePath = $request->thumbnail->storeAs('largeBanners',$bannerLargeName);

        $thumbnail = Image::make(Storage::get($thumbnailPath));
        $banner = Image::make(Storage::get($bannerPath));
        $bannerLarge = Image::make(Storage::get($bannerLargePath));

        $thumbnail->fit(335, 86, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $banner->fit(800, 400, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $bannerLarge->fit(1600, 800, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $thumbnail->save('storage/' . $thumbnailPath);
        $banner->save('storage/' . $bannerPath);
        $bannerLarge->save('storage/' . $bannerLargePath);

        $recipe->thumbnail = $thumbnailPath;
        $recipe->save();

        return $recipe;
    }
}