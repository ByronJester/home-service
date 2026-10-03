<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\DesignPictureStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BookingController extends Controller
{
    public function __construct(private DesignPictureStorage $designPictures) {}

    public function index(Request $request): Response
    {
        abort_unless(! $request->user()->is_admin, 403);

        $search = trim((string) $request->query('search', ''));

        $bookings = $request->user()->bookings()
            ->whereNotIn('status', ['Completed', 'Rejected'])
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
        $isAdmin = (bool) $request->user()->is_admin;

        $bookings = Booking::withTrashed()
            ->with('user:id,name,username,email')
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereIn('status', ['Completed', 'Rejected'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('detailed_address', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('body_parts', 'like', "%{$search}%")
                        ->orWhere('service_date', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return Inertia::render('History', [
            'bookings' => $bookings,
            'filters' => ['search' => $search],
            'isAdmin' => $isAdmin,
        ]);
    }

    public function schedules(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $search = trim((string) $request->query('search', ''));
        $bookings = Booking::query()
            ->with('user:id,name,username,email')
            ->where('status', 'Approved')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('detailed_address', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('body_parts', 'like', "%{$search}%")
                        ->orWhere('service_date', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('service_date')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Schedules', [
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

        $upload = $validated['design_picture'];

        if (! $upload instanceof UploadedFile) {
            return back()->withErrors([
                'design_picture' => 'The design picture could not be saved. Please try again.',
            ]);
        }

        try {
            $designPicture = $this->designPictures->store($upload);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'design_picture' => 'The design picture could not be saved. Please try again.',
            ]);
        }

        unset($validated['design_picture']);

        $request->user()->bookings()->create([
            ...$validated,
            'design_picture' => $designPicture,
        ]);

        return to_route('book-a-service')->with('success', 'Booking request submitted.');
    }

    public function adminIndex(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);

        $search = trim((string) $request->query('search', ''));
        $bookings = Booking::query()
            ->with('user:id,name,username,email')
            ->where('status', 'Pending')
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

        if ($validated['status'] === 'rejected') {
            $booking->update(['status' => 'Rejected']);
        } else {
            $booking->update([
                'status' => ucfirst($validated['status']),
            ]);
        }

        $destination = $validated['status'] === 'completed' ? 'schedules' : 'bookings';

        return to_route($destination)->with('success', "Booking {$validated['status']}.");
    }

    public function designPicture(Request $request, Booking $booking): HttpResponse
    {
        $this->authorizeDesignPictureAccess($request, $booking);

        abort_if(blank($booking->design_picture), 404);

        $image = $this->designPictures->fetch($booking->design_picture);

        return response($image['body'], 200, [
            'Content-Type' => $image['content_type'],
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadDesignPicture(Request $request, Booking $booking): HttpResponse
    {
        $this->authorizeDesignPictureAccess($request, $booking);

        abort_if(blank($booking->design_picture), 404);

        $image = $this->designPictures->fetch($booking->design_picture);

        return response($image['body'], 200, [
            'Content-Type' => $image['content_type'],
            'Content-Disposition' => 'attachment; filename='.$image['filename'],
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
