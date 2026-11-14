<?php

namespace App\Controller;

use App\Ticket\{Attachment, Ticket};
use App\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, JsonResponse, Request, Response, ResponseHeaderBag};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Pièces jointes des incidents. Contrôles : droit de lecture du ticket, taille maximale,
 * type MIME réel (détecté sur le contenu, pas sur l'extension) dans une liste blanche,
 * nom de stockage aléatoire, téléchargement forcé (jamais interprété par le navigateur).
 */
final class AttachmentController extends AbstractController
{
    public const MAX_SIZE = 5 * 1024 * 1024;
    public const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];

    public function __construct(
        private EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/var/uploads')] private string $uploadDir,
    ) {}

    #[Route('/api/tickets/{id}/attachments', name: 'api_attachment_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function list(Ticket $ticket): JsonResponse
    {
        $this->denyAccessUnlessGranted('TICKET_VIEW', $ticket);
        $items = $this->em->getRepository(Attachment::class)->findBy(['ticket' => $ticket], ['createdAt' => 'ASC']);
        return $this->json(array_map($this->serialize(...), $items));
    }

    #[Route('/api/tickets/{id}/attachments', name: 'api_attachment_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function upload(Ticket $ticket, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->denyAccessUnlessGranted('TICKET_VIEW', $ticket);
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['detail' => 'Fichier manquant ou invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($file->getSize() > self::MAX_SIZE) {
            return $this->json(['detail' => 'Fichier trop volumineux (5 Mo maximum).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return $this->json(['detail' => 'Type de fichier non autorisé (images, PDF ou texte).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stored = bin2hex(random_bytes(16));
        $size = $file->getSize();
        $original = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]/u', '_', basename($file->getClientOriginalName())) ?: 'fichier', 0, 200);
        $file->move($this->uploadDir, $stored);

        $attachment = new Attachment($ticket, $user, $original, $stored, $mime, $size);
        $ticket->touch();
        $this->em->persist($attachment);
        $this->em->flush();

        return $this->json($this->serialize($attachment), Response::HTTP_CREATED);
    }

    #[Route('/api/attachments/{id}/download', name: 'api_attachment_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Attachment $attachment): Response
    {
        $this->denyAccessUnlessGranted('TICKET_VIEW', $attachment->getTicket());
        $path = $this->uploadDir . '/' . $attachment->getStoredName();
        if (!is_file($path)) {
            return $this->json(['detail' => 'Fichier introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $attachment->getOriginalName(), 'fichier');
        return $response;
    }

    #[Route('/api/attachments/{id}', name: 'api_attachment_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Attachment $attachment, #[CurrentUser] User $user): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && $attachment->getUploader()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        $path = $this->uploadDir . '/' . $attachment->getStoredName();
        $this->em->remove($attachment);
        $this->em->flush();
        if (is_file($path)) {
            @unlink($path);
        }
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(Attachment $a): array
    {
        return [
            'id' => $a->getId(), 'name' => $a->getOriginalName(), 'mimeType' => $a->getMimeType(), 'size' => $a->getSize(),
            'uploader' => ['id' => $a->getUploader()->getId(), 'fullName' => $a->getUploader()->getFullName()],
            'createdAt' => $a->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
