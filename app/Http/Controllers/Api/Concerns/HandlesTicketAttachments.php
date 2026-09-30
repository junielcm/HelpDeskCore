<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Trait compartido para manejar archivos adjuntos de tickets y comentarios.
 *
 * Concentra las reglas de validación y el guardado físico de los archivos en
 * un solo lugar, de forma que los controladores que lo usan no repiten lógica
 * de almacenamiento ni definen reglas distintas entre sí.
 */
trait HandlesTicketAttachments
{
    protected int $maxAttachmentBytes = 5242880; // 5MB

    /**
     * Reglas de validación para el archivo adjunto (máx. 5MB).
     */
    protected function attachmentRules(): array
    {
        return ['sometimes', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'];
    }

    /**
     * Guarda un archivo adjunto y devuelve sus datos, o null si no viene archivo.
     *
     * Los archivos se guardan en el disco público bajo una carpeta por ticket
     * (storage/app/public/tickets/{ticketId}) y se conserva el nombre original
     * para mostrarlo al descargar.
     */
    protected function storeAttachment(
        UploadedFile $file,
        int $ticketId,
        ?int $commentId = null
    ): ?array {
        $path = Storage::disk('public')->putFile('tickets/'.$ticketId, $file);

        return [
            'ticket_id' => $ticketId,
            'comment_id' => $commentId,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ];
    }
}
