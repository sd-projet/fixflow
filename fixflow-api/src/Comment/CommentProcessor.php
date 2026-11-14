<?php

namespace App\Comment;

use ApiPlatform\Metadata\{Operation, Post};
use ApiPlatform\State\ProcessorInterface;
use App\Notification\TicketEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

final class CommentProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')] private ProcessorInterface $persist,
        private Security $security,
        private MessageBusInterface $bus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var Comment $data */
        $user = $this->security->getUser();
        $creation = $operation instanceof Post;

        if ($creation) {
            if (!$this->security->isGranted('TICKET_VIEW', $data->getTicket())) {
                throw new AccessDeniedHttpException('Incident inaccessible.');
            }
            $data->setAuthor($user);
            // Un commentaire est une activité : il repousse la détection d'inactivité.
            $data->getTicket()->touch();
        } else {
            $data->markEdited();
        }

        $result = $this->persist->process($data, $operation, $uriVariables, $context);

        if ($creation) {
            $this->bus->dispatch(new TicketEvent($data->getTicket()->getId(), 'commented', $user->getId()));
        }

        return $result;
    }
}
