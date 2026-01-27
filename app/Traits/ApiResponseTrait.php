<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    /**
     * Réponse de succès standard
     *
     * @param  mixed  $data
     */
    protected function successResponse($data = null, string $message = 'Opération réussie', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Réponse d'erreur standard
     */
    protected function errorResponse(string $message, int $statusCode = 400, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Réponse avec données paginées
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $message = 'Données récupérées avec succès'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Réponse pour création réussie
     *
     * @param  mixed  $data
     */
    protected function createdResponse($data, string $message = 'Ressource créée avec succès'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Réponse pour mise à jour réussie
     *
     * @param  mixed  $data
     */
    protected function updatedResponse($data, string $message = 'Ressource mise à jour avec succès'): JsonResponse
    {
        return $this->successResponse($data, $message);
    }

    /**
     * Réponse pour suppression réussie
     */
    protected function deletedResponse(string $message = 'Ressource supprimée avec succès'): JsonResponse
    {
        return $this->successResponse(null, $message);
    }

    /**
     * Réponse pour ressource non trouvée
     */
    protected function notFoundResponse(string $message = 'Ressource non trouvée'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Réponse pour accès non autorisé
     */
    protected function unauthorizedResponse(string $message = 'Accès non autorisé'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Réponse pour erreur de validation
     */
    protected function validationErrorResponse(array $errors, string $message = 'Données de validation incorrectes'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Appliquer la pagination avec filtres et recherche
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $searchColumns  Colonnes sur lesquelles effectuer la recherche
     * @param  array  $filterableColumns  Colonnes filtrables
     */
    protected function applyPaginationAndFilters($query, $request, array $searchColumns = [], array $filterableColumns = []): LengthAwarePaginator
    {
        // Recherche globale
        if ($request->filled('search') && ! empty($searchColumns)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchColumns, $searchTerm) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', "%{$searchTerm}%");
                }
            });
        }

        // Filtres spécifiques
        foreach ($filterableColumns as $column) {
            if ($request->filled($column)) {
                if (is_array($request->$column)) {
                    $query->whereIn($column, $request->$column);
                } else {
                    $query->where($column, $request->$column);
                }
            }
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100); // Max 100 par page

        return $query->paginate($perPage);
    }
}
