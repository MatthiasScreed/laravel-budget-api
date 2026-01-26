<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GamingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamingEventController extends Controller
{
    /**
     * Obtenir les événements gaming actifs et à venir
     */
    public function index(Request $request): JsonResponse
    {
        $activeEvents = GamingEvent::active()->get();
        $upcomingEvents = GamingEvent::upcoming()->limit(3)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'active_events' => $activeEvents->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                        'type' => $event->type,
                        'description' => $event->description,
                        'multiplier' => $event->multiplier,
                        'time_remaining' => $event->getTimeRemaining(),
                        'end_at' => $event->end_at,
                        'rewards' => $event->rewards,
                    ];
                }),
                'upcoming_events' => $upcomingEvents->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'name' => $event->name,
                        'type' => $event->type,
                        'description' => $event->description,
                        'multiplier' => $event->multiplier,
                        'starts_in' => $event->start_at->diffInSeconds(now()),
                        'start_at' => $event->start_at,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Créer un événement temporaire (admin seulement)
     */
    public function store(Request $request): JsonResponse
    {
        // Vérifier les permissions admin
        if (! $request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes',
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|max:30',
            'description' => 'nullable|string',
            'multiplier' => 'required|numeric|min:1|max:10',
            'duration_hours' => 'required|integer|min:1|max:168', // Max 1 semaine
            'conditions' => 'nullable|array',
            'rewards' => 'nullable|array',
        ]);

        $event = GamingEvent::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'multiplier' => $data['multiplier'],
            'conditions' => $data['conditions'] ?? null,
            'rewards' => $data['rewards'] ?? null,
            'start_at' => now(),
            'end_at' => now()->addHours($data['duration_hours']),
            'is_active' => true,
        ]);

        // Broadcaster l'événement à tous les utilisateurs connectés
        broadcast(new \App\Events\GamingEventStarted($event));

        return response()->json([
            'success' => true,
            'message' => 'Événement créé avec succès',
            'data' => $event,
        ], 201);
    }

    /**
     * Créer rapidement des événements prédéfinis
     */
    public function createQuickEvent(Request $request): JsonResponse
    {
        if (! $request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes',
            ], 403);
        }

        $type = $request->input('type');
        $duration = $request->input('duration_hours', 2);

        $event = match ($type) {
            'double_xp' => GamingEvent::createDoubleXpEvent($duration),
            'weekend_bonus' => GamingEvent::createWeekendBonus(),
            default => null
        };

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Type d\'événement non reconnu',
            ], 400);
        }

        broadcast(new \App\Events\GamingEventStarted($event));

        return response()->json([
            'success' => true,
            'message' => 'Événement rapide créé',
            'data' => $event,
        ], 201);
    }

    /**
     * Arrêter un événement prématurément
     */
    public function stop(Request $request, int $eventId): JsonResponse
    {
        if (! $request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Permissions insuffisantes',
            ], 403);
        }

        $event = GamingEvent::findOrFail($eventId);
        $event->update(['is_active' => false, 'end_at' => now()]);

        broadcast(new \App\Events\GamingEventEnded($event));

        return response()->json([
            'success' => true,
            'message' => 'Événement arrêté',
        ]);
    }
}
