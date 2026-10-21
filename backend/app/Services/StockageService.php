<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dépôt et restitution des fichiers d'archive.
 *
 * Disque : `filesystems.archive.disk` (local en v1, s3/MinIO ensuite).
 * Clé    : archives/AAAA/MM/<uuid>.<ext> — jamais le nom d'origine.
 * Les fichiers ne sont jamais supprimés physiquement par l'application :
 * la suppression d'un document est un soft delete.
 */
class StockageService
{
    public function disque(): Filesystem
    {
        return Storage::disk(config('filesystems.archive.disk'));
    }

    /**
     * Range le fichier et renvoie les attributs techniques du document.
     *
     * @return array{cle_stockage: string, nom_original: string, mime_type: string, extension: ?string, taille_octets: int, checksum_sha256: string}
     */
    public function stocker(UploadedFile $fichier): array
    {
        $checksum = hash_file('sha256', $fichier->getRealPath());
        $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension()) ?: null;
        $nom = (string) Str::uuid().($extension ? ".{$extension}" : '');
        $dossier = trim(config('filesystems.archive.root', 'archives'), '/').'/'.now()->format('Y/m');

        $cle = $this->disque()->putFileAs($dossier, $fichier, $nom);
        if ($cle === false) {
            throw new RuntimeException("Écriture impossible sur le disque d'archive.");
        }

        return [
            'cle_stockage'    => $cle,
            'nom_original'    => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'mime_type'       => $fichier->getMimeType() ?: 'application/octet-stream',
            'extension'       => $extension,
            'taille_octets'   => (int) $fichier->getSize(),
            'checksum_sha256' => $checksum,
        ];
    }

    public function telecharger(Document $document, bool $enLigne = false): StreamedResponse
    {
        $disque = $this->disque();
        $entetes = ['Content-Type' => $document->mime_type];

        if ($enLigne) {
            return $disque->response($document->cle_stockage, $document->nom_original, $entetes, 'inline');
        }

        return $disque->download($document->cle_stockage, $document->nom_original, $entetes);
    }

    /** Recalcule l'empreinte du fichier stocké et la compare à celle en base. */
    public function verifierIntegrite(Document $document): array
    {
        $flux = $this->disque()->readStream($document->cle_stockage);
        if (! $flux) {
            return ['present' => false, 'integre' => false, 'checksum_calcule' => null];
        }

        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $flux);
        fclose($flux);
        $calcule = hash_final($ctx);

        return [
            'present'          => true,
            'integre'          => hash_equals($document->checksum_sha256, $calcule),
            'checksum_calcule' => $calcule,
        ];
    }
}
