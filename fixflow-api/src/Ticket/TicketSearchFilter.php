<?php

namespace App\Ticket;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/** Recherche plein texte simple : `?q=...` sur la référence OU le titre. */
final class TicketSearchFilter implements FilterInterface
{
    public function apply(QueryBuilder $qb, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $q = trim((string) ($context['filters']['q'] ?? ''));
        if ($q === '') {
            return;
        }
        $a = $qb->getRootAliases()[0];
        $qb->andWhere($qb->expr()->orX(
            $qb->expr()->like("LOWER($a.title)", ':fixflow_q'),
            $qb->expr()->like("LOWER($a.reference)", ':fixflow_q'),
        ))->setParameter('fixflow_q', '%' . addcslashes(mb_strtolower($q), '%_\\') . '%');
    }

    public function getDescription(string $resourceClass): array
    {
        return ['q' => [
            'property' => null, 'type' => 'string', 'required' => false,
            'description' => 'Recherche sur la référence ou le titre',
        ]];
    }
}
