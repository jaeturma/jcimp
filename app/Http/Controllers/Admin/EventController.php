<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    /**
     * POST /api/admin/events/{event}/cover
     * Upload a ticket label / cover image for the event.
     */
    public function uploadCover(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        // Delete old cover
        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }

        $path = $request->file('cover')->store('event-covers', 'public');

        $event->update(['cover_image' => $path]);

        return response()->json([
            'cover_image' => $path,
            'cover_url'   => asset('storage/' . $path),
        ]);
    }

    /**
     * GET /api/admin/events
     * List events with filter/search/pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::orderByDesc('event_date')
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('venue', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            }))
            ->when(!is_null($request->is_active) && $request->is_active !== '', fn ($q) => $q->where('is_active', $request->is_active));

        $perPage = intval($request->per_page) > 0 ? intval($request->per_page) : 10;
        $events = $query->paginate($perPage)->withQueryString();

        $events->transform(fn($e) => [
            'id'          => $e->id,
            'name'        => $e->name,
            'description' => $e->description,
            'venue'       => $e->venue,
            'event_date'  => $e->event_date?->toISOString(),
            'is_active'   => $e->is_active,
            'cover_image' => $e->cover_image,
            'cover_url'   => $e->cover_image ? asset('storage/' . $e->cover_image) : null,
        ]);

        return response()->json($events);
    }

    /**
     * GET /api/admin/events/{event}
     * Show a single event.
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'id'          => $event->id,
            'name'        => $event->name,
            'description' => $event->description,
            'venue'       => $event->venue,
            'event_date'  => $event->event_date?->toISOString(),
            'is_active'   => $event->is_active,
            'cover_image' => $event->cover_image,
            'cover_url'   => $event->cover_image ? asset('storage/' . $event->cover_image) : null,
        ]);
    }

    /**
     * POST /api/admin/events
     * Create a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string|max:255',
            'event_date' => 'required|date',
            'is_active' => 'boolean',
        ]);

        $event = Event::create(array_merge($data, ['is_active' => $request->boolean('is_active', false)]));

        return response()->json(['event' => $event], 201);
    }

    /**
     * PUT /api/admin/events/{event}
     * Update an event.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string|max:255',
            'event_date' => 'required|date',
            'is_active' => 'boolean',
        ]);

        $event->update(array_merge($data, ['is_active' => $request->boolean('is_active', false)]));

        return response()->json(['event' => $event]);
    }

    /**
     * DELETE /api/admin/events/{event}
     */
    public function destroy(Event $event): JsonResponse
    {
        if ($event->tickets()->exists()) {
            return response()->json(['message' => 'Delete associated ticket tiers first.'], 422);
        }

        // remove cover image file
        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }
}
