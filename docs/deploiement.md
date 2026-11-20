# Déploiement : Render (API + PostgreSQL) et Vercel (frontend)

```
Navigateur ─▶ Vercel (React statique)
                 └─ /api/* ──(rewrite)──▶ Render : API Symfony ─▶ PostgreSQL Render
```

Vercel relaie `/api/*` vers Render : le navigateur ne parle qu'à **un seul domaine**. Le cookie de session reste donc « first-party » (aucun CORS, aucun problème de cookies tiers).

## 0. Prérequis
- Le projet est dans un dépôt Git (GitHub ou GitLab) avec, à la racine du dépôt, `fixflow-api/` et `fixflow-front/`.
- **`fixflow-api/composer.json`, `composer.lock` et `symfony.lock` sont commités** (générés au premier `docker compose up`). `vendor/` et `var/` ne le sont pas.
- Les fichiers de déploiement de ce lot sont en place : `render.yaml`, `fixflow-api/Dockerfile.prod`, `fixflow-api/docker-entrypoint.prod.sh`, `fixflow-front/vercel.json`.

## 1. Render (API + base)
1. Render → **New → Blueprint** → connecter le dépôt → Render lit `render.yaml` (base `fixflow-db` + service `fixflow-api`).
2. Renseigner les deux variables demandées :
   - `FIXFLOW_ADMIN_EMAIL` : l'email de ton compte administrateur ;
   - `FIXFLOW_ADMIN_PASSWORD` : un mot de passe fort (8 caractères minimum).
3. Lancer le déploiement (le premier build prend quelques minutes).
4. Quand le service est « Live », note son URL, par ex. `https://fixflow-api-xxxx.onrender.com`, et vérifie : `https://…onrender.com/api/docs`.

`FIXFLOW_SEED=1` crée en plus les comptes et incidents de démonstration (`admin@fixflow.test`… mot de passe **public** `password`). Pratique pour une démo, à passer à `0` pour un usage réel.

## 2. Vercel (frontend)
1. Dans `fixflow-front/vercel.json`, remplace `https://fixflow-api.onrender.com` par **l'URL réelle** de ton service Render, puis commit et push.
2. Vercel → **Add New → Project** → importer le dépôt.
3. **Root Directory : `fixflow-front`** ; Framework : Vite (détecté) ; Build : `npm run build` ; Output : `dist`.
4. Deploy. Ouvre l'URL Vercel, puis connecte-toi.

## 3. Vérifications
- La landing s'affiche, « Se connecter » fonctionne, le tableau de bord se charge.
- Actualiser une page profonde (`/tickets/1`) ne donne pas de 404 (rewrite SPA).
- Créer un incident, ajouter un commentaire, vérifier l'historique et les notifications.
- Tester un téléversement de pièce jointe (voir limites ci-dessous).

## Limites du plan gratuit (à connaître avant une démo)
- **Mise en veille** : l'API Render gratuite s'endort après un moment d'inactivité ; la première requête suivante peut prendre une trentaine de secondes. Ouvre l'API (`/api/docs`) quelques minutes avant une présentation.
- **Pièces jointes non persistantes** : le disque du plan gratuit est éphémère, les fichiers disparaissent à chaque redéploiement/redémarrage. Pour les conserver : disque persistant Render, ou stockage objet (S3 / Cloudflare R2).
- **Pas de worker ni de cron gratuits** : `MESSENGER_TRANSPORT_DSN=sync://` traite les notifications immédiatement dans la requête. Les alertes d'inactivité (`app:tickets:alerts`) demandent un Cron Job Render payant ou un déclencheur externe.
- **Base gratuite** : vérifie la durée de vie et les limites actuelles dans le tableau de bord Render.
- **Taille des fichiers via Vercel** : le relais Vercel peut imposer ses propres limites de requête ; teste un fichier proche de 5 Mo.

## Pour aller plus loin
- Générer de vraies migrations (`doctrine:migrations:diff`) et les commiter : l'entrypoint les appliquera automatiquement à la place de `schema:update`.
- Domaine personnalisé sur Vercel ; en cas de changement d'URL Render, mettre à jour `vercel.json`.
- Plan payant Render (service toujours actif, worker Messenger, cron, disque) pour un usage réel.
