# FixFlow — gestion des incidents et du support technique

Application SaaS pour centraliser les incidents techniques : déclaration, attribution, suivi, commentaires, historique d'audit, tableaux de bord par rôle, notifications et automatisations.

| | |
|---|---|
| Backend | PHP 8.4 · Symfony 6.4 · API Platform 3 · Doctrine · PostgreSQL 16 · Messenger |
| Frontend | React 18 · TypeScript · Vite · TanStack Query |
| Outillage | Docker Compose · PHPUnit · Vitest · GitLab CI |

## Démarrage (Windows 11, macOS, Linux)

Prérequis : Docker Desktop. Rien d'autre à installer (pas de PHP ni Composer en local).

```bash
docker compose up --build
```

Le **premier lancement** télécharge Symfony et ses dépendances (quelques minutes) ; le conteneur `api` génère le squelette, crée le schéma PostgreSQL et charge les données de démonstration. Ensuite :

- Application : http://localhost:5173
- API et documentation OpenAPI : http://localhost:8000/api/docs

Comptes de démonstration (mot de passe `password`) : `admin@fixflow.test`, `dev@fixflow.test`, `dev2@fixflow.test`, `user@fixflow.test`, `user2@fixflow.test`.

Services : `db` (PostgreSQL) · `api` (Symfony) · `worker` (consommateur Messenger → notifications) · `alerts` (alertes d'inactivité, 1×/jour) · `front` (Vite).

## Tests

```bash
# Backend (base dédiée fixflow_test, jamais la base de dev)
docker compose exec -e APP_ENV=test api sh -c "php bin/console doctrine:database:create --if-not-exists && vendor/bin/phpunit --testdox"

# Frontend
docker compose exec front npm test
```

Couverture : authentification, matrice de permissions par rôle, visibilité des données, attribution automatique, historique, archivage, recherche/filtres/pagination, commentaires, validation, `TicketVoter` (unitaire), client API et utilitaires front.

## Commandes utiles

```bash
docker compose exec api php bin/console app:tickets:alerts --days=3   # alertes manuelles
docker compose exec api php bin/console app:seed                      # (re)créer les comptes de démo
docker compose exec api php bin/console doctrine:migrations:diff      # générer une migration
```

> En développement le schéma est synchronisé automatiquement (`schema:update`) tant que `migrations/` est vide.
> Dès que vous générez la première migration, l'entrypoint bascule sur `doctrine:migrations:migrate`.

## Documentation

- [docs/architecture.md](docs/architecture.md) : architecture, modèle de données (diagramme ER), matrice de permissions, choix techniques, avancement par phase.
- `/api/docs` : référence interactive de l'API.

## Structure

```
fixflow-api/    Symfony + API Platform (code organisé par domaine : User, Ticket, Comment, Notification, Dashboard, Security)
fixflow-front/  React + TypeScript (features/, pages/, components/, services/, lib/)
docker-compose.yml · .gitlab-ci.yml
```
