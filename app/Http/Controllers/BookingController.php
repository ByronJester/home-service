<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $bookings = $request->user()->bookings()
            ->where('status', '!=', 'Completed')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('detailed_address', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('body_parts', 'like', "%{$search}%")
                        ->orWhere('service_date', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return Inertia::render('BookAService', [
            'bookings' => $bookings,
            'filters' => ['search' => $search],
            'unavailableDates' => Booking::query()
                ->whereIn('status', ['Pending', 'Approved'])
                ->pluck('service_date')
                ->map(fn ($date) => $date->format('Y-m-d'))
                ->values(),
        ]);
    }

    public function history(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $bookings = $request->user()->bookings()
            ->where('status', 'Completed')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('detailed_address', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('body_parts', 'like', "%{$search}%")
                        ->orWhere('service_date', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return Inertia::render('History', [
            'bookings' => $bookings,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'detailed_address' => ['required', 'string', 'max:2000'],
            'contact_number' => ['required', 'string', 'regex:/^\\+1 \\(818\\)-[0-9]{3}-[0-9]{4}$/'],
            'body_parts' => ['required', 'string', 'max:255'],
            'design_picture' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'service_date' => [
                'required',
                'date',
                'after_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $isUnavailable = Booking::query()
                        ->whereDate('service_date', $value)
                        ->whereIn('status', ['Pending', 'Approved'])
                        ->exists();

                    if ($isUnavailable) {
                        $fail('The selected service date is already booked.');
                    }
                },
            ],
            'price_range' => ['required', 'integer', 'min:20', 'max:2000'],
        ]);

        $designPicture = $validated['design_picture'];
        $filename = Str::random(15).'.'.$designPicture->getClientOriginalExtension();
        $storedPath = $designPicture->storeAs('booking-designs', $filename, 'local');

        if ($storedPath === false) {
            return back()->withErrors([
                'design_picture' => 'The design picture could not be saved. Please try again.',
            ]);
        }

        unset($validated['design_picture']);

        $request->user()->bookings()->create([
            ...$validated,
            'design_picture' => $filename,
        ]);

        return to_route('book-a-service')->with('success', 'Booking request submitted.');
    }

    public function adminIndex(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $search = trim((string) $request->query('search', ''));
        $bookings = Booking::query()
            ->with('user:id,name,username,email')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('detailed_address', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('body_parts', 'like', "%{$search}%")
                        ->orWhere('service_date', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Bookings', [
            'bookings' => $bookings,
            'filters' => ['search' => $search],
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,completed'],
        ]);

        $validTransition = match ($validated['status']) {
            'approved', 'rejected' => $booking->status === 'Pending',
            'completed' => $booking->status === 'Approved',
        };

        if (! $validTransition) {
            return back()->withErrors([
                'status' => 'This booking can no longer be moved to that status.',
            ]);
        }

        $booking->update([
            'status' => ucfirst($validated['status']),
        ]);

        return to_route('bookings')->with('success', "Booking {$validated['status']}.");
    }

    public function designPicture(Request $request, Booking $booking)
    {
        $this->authorizeDesignPictureAccess($request, $booking);

        abort_if(blank($booking->design_picture), 404);

        $path = 'booking-designs/'.$booking->design_picture;
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $booking->design_picture, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadDesignPicture(Request $request, Booking $booking)
    {
        $this->authorizeDesignPictureAccess($request, $booking);

        abort_if(blank($booking->design_picture), 404);

        $path = 'booking-designs/'.$booking->design_picture;
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $booking->design_picture, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeDesignPictureAccess(Request $request, Booking $booking): void
    {
        abort_unless(
            $request->user()->is_admin || $request->user()->id === $booking->user_id,
            403,
        );
    }
}
