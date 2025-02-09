<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class UserController extends AbstractController
{
    private string $filePath = __DIR__ . '/../DB/users.json';

    private function loadUsers(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        return json_decode(file_get_contents($this->filePath), true) ?? [];
    }

    private function saveUsers(array $users): void
    {
        file_put_contents($this->filePath, json_encode($users, JSON_PRETTY_PRINT));
    }

    private function jsonResponse(mixed $data, int $status = 200, ?string $message = null): JsonResponse
    {
        $response = ['data' => $data];
        if ($message) {
            $response['meta'] = ['message' => $message];
        }
        return $this->json($response, $status);
    }

    #[Route('/api/users', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $users = $this->loadUsers();
        $limit = max(1, $request->query->getInt('limit', 10));
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $limit;
        $paginatedUsers = array_slice($users, $offset, $limit, true);

        return $this->jsonResponse([
            'users' => array_values($paginatedUsers),
            'pagination' => [
                'current_page' => $page,
                'limit' => $limit,
                'total' => count($users),
            ],
        ]);
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $users = $this->loadUsers();
        if (!isset($users[$id])) {
            return $this->jsonResponse(null, 404, 'User not found');
        }
        return $this->jsonResponse($users[$id]);
    }

    #[Route('/api/users', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $users = $this->loadUsers();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(null, 400, 'Invalid name or email format');
        }

        $id = Uuid::v4()->toRfc4122();
        $users[$id] = ['id' => $id, 'name' => $data['name'], 'email' => $data['email']];

        $this->saveUsers($users);

        return $this->jsonResponse($users[$id], 201, 'User created successfully');
    }

    #[Route('/api/users/{id}', methods: ['PUT'])]
    public function replace(string $id, Request $request): JsonResponse
    {
        $users = $this->loadUsers();
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(null, 400, 'Invalid name or email format');
        }

        $users[$id] = ['id' => $id, 'name' => $data['name'], 'email' => $data['email']];
        $this->saveUsers($users);

        return $this->jsonResponse($users[$id], 200, 'User updated successfully');
    }

    #[Route('/api/users/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $users = $this->loadUsers();
        if (!isset($users[$id])) {
            return $this->jsonResponse(null, 404, 'User not found');
        }

        $data = json_decode($request->getContent(), true);
        $allowedFields = ['name', 'email'];
        $data = array_intersect_key($data, array_flip($allowedFields));

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(null, 400, 'Invalid email format');
        }

        $users[$id] = array_merge($users[$id], $data);
        $this->saveUsers($users);

        return $this->jsonResponse($users[$id], 200, 'User updated successfully');
    }

    #[Route('/api/users/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $users = $this->loadUsers();
        if (!isset($users[$id])) {
            return $this->jsonResponse(null, 404, 'User not found');
        }

        unset($users[$id]);
        $this->saveUsers($users);

        return new JsonResponse(null, 204);
    }

    #[Route('/api/users', methods: ['OPTIONS'])]
    #[Route('/api/users/{id}', methods: ['OPTIONS'])]
    public function options(): JsonResponse
    {
        return new JsonResponse(null, 204, [
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);
    }
}
