<?php

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/AnnouncementService.php';

class AnnouncementController
{
    private AnnouncementService $service;

    public function __construct()
    {
        $this->service = new AnnouncementService();
    }

    public function listPublic(array $query): void
    {
        [$limit, $offset] = $this->extractPagination($query, 100, 0, 100);
        $items = $this->service->listPublic($limit, $offset);
        $total = $this->service->countAll();

        json_success(null, [
            'announcements' => $this->mapItems($items),
            'total' => $total,
        ]);
    }

    public function listAdmin(array $query): void
    {
        [$limit, $offset] = $this->extractPagination($query, 100, 0, 200);
        $items = $this->service->listAdmin($limit, $offset);
        $total = $this->service->countAll();

        json_success(null, [
            'announcements' => $this->mapItems($items, true),
            'total' => $total,
        ]);
    }

    public function create(array $data, int $userId): void
    {
        $payload = $this->validatePayload($data);
        $id = $this->service->create($payload, $userId);
        $item = $this->service->findById($id);

        json_success('Annonce creee', [
            'announcement' => $item ? $this->mapItem($item) : null,
        ], 201);
    }

    public function update(array $data, int $userId): void
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            json_error('Identifiant annonce invalide', 400);
        }

        $payload = $this->validatePayload($data);
        $updated = $this->service->update($id, $payload, $userId);
        if (!$updated) {
            json_error('Annonce non trouvee', 404);
        }

        $item = $this->service->findById($id);
        json_success('Annonce mise a jour', [
            'announcement' => $item ? $this->mapItem($item) : null,
        ]);
    }

    public function delete(array $data): void
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            json_error('Identifiant annonce invalide', 400);
        }

        $deleted = $this->service->delete($id);
        if (!$deleted) {
            json_error('Annonce non trouvee', 404);
        }

        json_success('Annonce supprimee');
    }

    private function extractPagination(array $query, int $defaultLimit, int $defaultOffset, int $maxLimit): array
    {
        $limit = isset($query['limit']) ? (int)$query['limit'] : $defaultLimit;
        $offset = isset($query['offset']) ? (int)$query['offset'] : $defaultOffset;

        if ($limit <= 0) {
            $limit = $defaultLimit;
        }
        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        return [$limit, $offset];
    }

    private function validatePayload(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $buttonLabel = trim((string)($data['buttonLabel'] ?? ''));
        $buttonLink = trim((string)($data['buttonLink'] ?? ''));
        $publishedAtRaw = trim((string)($data['publishedAt'] ?? ''));

        if ($title === '' || $message === '') {
            json_error('Titre et message sont obligatoires', 400);
        }
        if (mb_strlen($title) > 160) {
            json_error('Titre trop long (max 160 caracteres)', 400);
        }
        if (mb_strlen($message) > 5000) {
            json_error('Message trop long (max 5000 caracteres)', 400);
        }

        $buttonLabelProvided = $buttonLabel !== '';
        $buttonLinkProvided = $buttonLink !== '';
        if ($buttonLabelProvided xor $buttonLinkProvided) {
            json_error('Le bouton doit contenir un libelle et un lien', 400);
        }
        if ($buttonLinkProvided && !$this->isValidButtonLink($buttonLink)) {
            json_error('Lien du bouton invalide', 400);
        }

        $publishedAt = $this->normalizePublishedAt($publishedAtRaw);
        if ($publishedAt === null) {
            json_error('Date de publication invalide', 400);
        }

        return [
            'title' => $title,
            'message' => $message,
            'button_label' => $buttonLabelProvided ? $buttonLabel : null,
            'button_link' => $buttonLinkProvided ? $buttonLink : null,
            'published_at' => $publishedAt,
        ];
    }

    private function isValidButtonLink(string $link): bool
    {
        if (preg_match('#^https?://#i', $link)) {
            return filter_var($link, FILTER_VALIDATE_URL) !== false;
        }

        return str_starts_with($link, '/');
    }

    private function normalizePublishedAt(string $input): ?string
    {
        if ($input === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($input);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function mapItems(array $items, bool $includeAdmin = false): array
    {
        $mapped = [];
        foreach ($items as $item) {
            $mapped[] = $this->mapItem($item, $includeAdmin);
        }

        return $mapped;
    }

    private function mapItem(array $item, bool $includeAdmin = false): array
    {
        $mapped = [
            'id' => (int)$item['id'],
            'title' => (string)$item['title'],
            'message' => (string)$item['message'],
            'buttonLabel' => $item['button_label'] !== null ? (string)$item['button_label'] : '',
            'buttonLink' => $item['button_link'] !== null ? (string)$item['button_link'] : '',
            'publishedAt' => (string)$item['published_at'],
            'createdAt' => (string)$item['created_at'],
            'updatedAt' => (string)$item['updated_at'],
        ];

        if ($includeAdmin) {
            $mapped['authorUserId'] = isset($item['author_user_id']) ? (int)$item['author_user_id'] : 0;
            $mapped['updatedByUserId'] = isset($item['updated_by_user_id']) ? (int)$item['updated_by_user_id'] : 0;
        }

        return $mapped;
    }
}

