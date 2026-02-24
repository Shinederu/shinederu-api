<?php

require_once __DIR__ . '/DatabaseService.php';

class AnnouncementService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = MainSiteDatabaseService::getInstance();
    }

    public function listPublic(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, message, button_label, button_link, published_at, created_at, updated_at
             FROM main_site_announcements
             ORDER BY published_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listAdmin(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, message, button_label, button_link, published_at, created_at, updated_at, author_user_id, updated_by_user_id
             FROM main_site_announcements
             ORDER BY published_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) AS c FROM main_site_announcements');
        $row = $stmt->fetch();

        return (int)($row['c'] ?? 0);
    }

    public function create(array $data, int $authorUserId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO main_site_announcements
            (title, message, button_label, button_link, published_at, author_user_id, updated_by_user_id)
            VALUES (:title, :message, :button_label, :button_link, :published_at, :author_user_id, :updated_by_user_id)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'message' => $data['message'],
            'button_label' => $data['button_label'],
            'button_link' => $data['button_link'],
            'published_at' => $data['published_at'],
            'author_user_id' => $authorUserId,
            'updated_by_user_id' => $authorUserId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data, int $updatedByUserId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE main_site_announcements
             SET title = :title,
                 message = :message,
                 button_label = :button_label,
                 button_link = :button_link,
                 published_at = :published_at,
                 updated_by_user_id = :updated_by_user_id
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'message' => $data['message'],
            'button_label' => $data['button_label'],
            'button_link' => $data['button_link'],
            'published_at' => $data['published_at'],
            'updated_by_user_id' => $updatedByUserId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM main_site_announcements WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, message, button_label, button_link, published_at, created_at, updated_at
             FROM main_site_announcements
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
