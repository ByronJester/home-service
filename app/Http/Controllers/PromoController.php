<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PromoController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $search = trim((string) $request->query('search', ''));

        $promos = Promo::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('usage', 'like', "%{$search}%")
                        ->orWhere('discount', 'like', "%{$search}%")
                        ->orWhere('requirements', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->get();

        return Inertia::render('Promos', [
            'promos' => $promos,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:5000'],
            'discount' => ['required', 'integer', 'min:1', 'max:100'],
            'requirements' => ['required', 'array', 'min:1', 'max:20'],
            'requirements.*' => ['required', 'string', 'max:255'],
            'usage' => ['required', 'in:Limited Availability,Unlimited Availability'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $image = $validated['image'];

        if (! $image instanceof UploadedFile) {
            return back()->withErrors([
                'image' => 'The promo image could not be saved. Please try again.',
            ]);
        }

        $stored = $this->storePromoImage($image);

        if ($stored === null) {
            return back()->withErrors([
                'image' => 'The promo image could not be saved. Please try again.',
            ]);
        }

        Promo::query()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'discount' => $validated['discount'],
            'requirements' => $this->requirementList($validated['requirements']),
            'usage' => $validated['usage'],
            'image' => $stored,
            'is_active' => true,
        ]);

        return back();
    }

    public function revise(Request $request, Promo $promo): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'description' => ['required', 'string', 'max:5000'],
            'discount' => ['required', 'integer', 'min:1', 'max:100'],
            'requirements' => ['required', 'array', 'min:1', 'max:20'],
            'requirements.*' => ['required', 'string', 'max:255'],
            'usage' => ['required', 'in:Limited Availability,Unlimited Availability'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $imagePath = $promo->image;
        $image = $validated['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $stored = $this->storePromoImage($image);

            if ($stored === null) {
                return back()->withErrors([
                    'image' => 'The promo image could not be saved. Please try again.',
                ]);
            }

            $imagePath = $stored;
        }

        $promo->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'discount' => $validated['discount'],
            'requirements' => $this->requirementList($validated['requirements']),
            'usage' => $validated['usage'],
            'image' => $imagePath,
        ]);

        return back();
    }

    public function destroy(Request $request, Promo $promo): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $promo->delete();

        return back();
    }

    public function update(Request $request, Promo $promo): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $promo->update([
            'is_active' => $validated['is_active'],
        ]);

        return back();
    }

    private function storePromoImage(UploadedFile $image): ?string
    {
        $filename = Str::random(15).'.'.$image->extension();
        $stored = $image->storeAs('images/promos', $filename, 'public');

        if ($stored === false) {
            return null;
        }

        return '/storage/'.$stored;
    }

    /**
     * @param  array<mixed>  $requirements
     * @return list<string>
     */
    private function requirementList(array $requirements): array
    {
        $list = [];

        foreach ($requirements as $requirement) {
            if (is_string($requirement) && $requirement !== '') {
                $list[] = $requirement;
            }
        }

        return $list;
    }
}
