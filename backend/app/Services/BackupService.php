<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');

        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * Crée une copie cohérente de la base SQLite (VACUUM INTO).
     */
    public function create(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException(__('La sauvegarde rapide est disponible uniquement pour SQLite.'));
        }

        $dir = $this->directory();
        $name = 'backup-'.now()->format('Y-m-d_His').'.sqlite';
        $path = $dir.DIRECTORY_SEPARATOR.$name;

        $target = str_replace("'", "''", $path);

        DB::statement("VACUUM INTO '{$target}'");

        return $this->describe($path);
    }

    public function all(): array
    {
        $items = [];

        foreach (File::files($this->directory()) as $file) {
            if ($file->getExtension() !== 'sqlite') {
                continue;
            }

            $items[] = $this->describe($file->getPathname());
        }

        usort($items, fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $items;
    }

    public function path(string $name): string
    {
        $safe = basename($name);

        if (! preg_match('/^backup-[0-9A-Za-z_.-]+\.sqlite$/', $safe)) {
            throw new RuntimeException(__('Nom de sauvegarde invalide.'));
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$safe;

        if (! File::exists($path)) {
            throw new RuntimeException(__('Sauvegarde introuvable.'));
        }

        return $path;
    }

    public function delete(string $name): void
    {
        File::delete($this->path($name));
    }

    /**
     * Ne conserve que les $keep sauvegardes les plus récentes.
     */
    public function prune(int $keep = 14): int
    {
        $removed = 0;

        foreach (array_slice($this->all(), $keep) as $backup) {
            File::delete($this->directory().DIRECTORY_SEPARATOR.$backup['name']);
            $removed++;
        }

        return $removed;
    }

    private function describe(string $path): array
    {
        return [
            'name' => basename($path),
            'size' => File::size($path),
            'created_at' => date(DATE_ATOM, File::lastModified($path)),
        ];
    }
}
