<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    public function index(Request $request): Response
    {
        $ingredients = Ingredient::query()
            ->withCount('items')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('supplier', 'like', "%{$term}%")))
            ->when($request->string('category')->toString(), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Ingredient $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->unit,
                'category' => $i->category,
                'supplier' => $i->supplier,
                'last_price' => (float) $i->last_price,
                'last_purchased_on' => $i->last_purchased_on?->format('M j, Y'),
                'notes' => $i->notes,
                'is_active' => $i->is_active,
                'times_bought' => $i->items_count,
            ]);

        return Inertia::render('Admin/Ingredients/Index', [
            'ingredients' => $ingredients,
            'units' => Ingredient::UNITS,
            'categories' => Ingredient::CATEGORIES,
            'filters' => $request->only('q', 'category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Ingredient::create($data);

        return back()->with('success', "{$data['name']} added to the ingredient list.");
    }

    public function update(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $data = $this->validated($request, $ingredient);

        if ($data['name'] !== $ingredient->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $ingredient->id);
        }

        $ingredient->update($data);

        return back()->with('success', "{$ingredient->name} updated.");
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        // Past purchase lines keep their own name and price snapshot, so
        // removing an ingredient never rewrites what a month actually cost.
        $name = $ingredient->name;
        $ingredient->delete();

        return back()->with('success', "{$name} removed.");
    }

    private function validated(Request $request, ?Ingredient $ingredient = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('ingredients', 'name')->ignore($ingredient?->id),
            ],
            'unit' => ['required', 'string', Rule::in(Ingredient::UNITS)],
            'category' => ['required', 'string', Rule::in(Ingredient::CATEGORIES)],
            'supplier' => ['nullable', 'string', 'max:255'],
            'last_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'ingredient';
        $slug = $base;
        $i = 2;

        while (Ingredient::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
