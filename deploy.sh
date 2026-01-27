#!/bin/bash

################################################################################
# CoinQuest API - Script de déploiement production
# Version: 1.0.0
# Description: Script automatisé pour déployer l'API en production
################################################################################

set -e  # Arrêter en cas d'erreur

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonctions utilitaires
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_header() {
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "  $1"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
}

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "artisan" ]; then
    print_error "Erreur: Ce script doit être exécuté depuis la racine du projet Laravel"
    exit 1
fi

print_header "🚀 COINQUEST API - DÉPLOIEMENT PRODUCTION"

# Vérifier l'environnement
if [ ! -f ".env" ]; then
    print_error "Fichier .env manquant"
    print_info "Copiez .env.production et configurez les variables"
    exit 1
fi

# Lire l'environnement
APP_ENV=$(grep APP_ENV .env | cut -d '=' -f2)
print_info "Environnement détecté: $APP_ENV"

if [ "$APP_ENV" != "production" ]; then
    print_warning "L'environnement n'est pas en production"
    read -p "Continuer quand même? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Déploiement annulé"
        exit 0
    fi
fi

print_header "1. MISE À JOUR DU CODE"

# Git pull
if [ -d ".git" ]; then
    print_info "Récupération des dernières modifications..."
    git pull origin main || git pull origin master
    print_success "Code mis à jour"
else
    print_warning "Pas de dépôt Git détecté"
fi

print_header "2. INSTALLATION DES DÉPENDANCES"

# Composer
print_info "Installation des dépendances PHP..."
composer install --optimize-autoloader --no-dev --no-interaction
print_success "Dépendances PHP installées"

# NPM (si nécessaire)
if [ -f "package.json" ]; then
    print_info "Installation des dépendances Node..."
    npm ci --production
    npm run build
    print_success "Assets frontend compilés"
fi

print_header "3. MAINTENANCE MODE"

print_info "Activation du mode maintenance..."
php artisan down --retry=60 --secret="$(openssl rand -base64 32)"
print_success "Mode maintenance activé"

print_header "4. OPTIMISATIONS"

print_info "Nettoyage des caches..."
php artisan optimize:clear
print_success "Caches nettoyés"

print_info "Optimisation pour production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
print_success "Application optimisée"

print_header "5. BASE DE DONNÉES"

# Backup de la base de données
print_info "Sauvegarde de la base de données recommandée avant migration"
read -p "Sauvegarder la base de données? (Y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]] || [[ -z $REPLY ]]; then
    DB_CONNECTION=$(grep DB_CONNECTION .env | cut -d '=' -f2)
    DB_DATABASE=$(grep DB_DATABASE .env | cut -d '=' -f2)
    BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"

    if [ "$DB_CONNECTION" = "mysql" ]; then
        DB_USERNAME=$(grep DB_USERNAME .env | cut -d '=' -f2)
        DB_PASSWORD=$(grep DB_PASSWORD .env | cut -d '=' -f2)
        DB_HOST=$(grep DB_HOST .env | cut -d '=' -f2)

        print_info "Création du backup: $BACKUP_FILE"
        mysqldump -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "storage/backups/$BACKUP_FILE" 2>/dev/null || {
            print_warning "Impossible de créer le backup automatiquement"
        }
    fi
fi

# Migrations
print_warning "Les migrations vont être exécutées sur la base de données de production"
read -p "Continuer? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_info "Exécution des migrations..."
    php artisan migrate --force
    print_success "Migrations exécutées"
else
    print_warning "Migrations ignorées"
fi

print_header "6. PERMISSIONS"

print_info "Configuration des permissions..."
chmod -R 775 storage bootstrap/cache
print_success "Permissions configurées"

print_header "7. STORAGE"

print_info "Création du lien symbolique storage..."
php artisan storage:link
print_success "Storage lié"

print_header "8. VÉRIFICATIONS"

# Test de santé
print_info "Test de l'API..."
if command -v curl &> /dev/null; then
    APP_URL=$(grep APP_URL .env | cut -d '=' -f2)
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL/api/health" || echo "000")

    if [ "$HTTP_STATUS" = "200" ]; then
        print_success "API fonctionnelle (Status: $HTTP_STATUS)"
    else
        print_warning "API potentiellement non fonctionnelle (Status: $HTTP_STATUS)"
    fi
else
    print_warning "curl non installé, impossible de tester l'API"
fi

# Vérifier les tables critiques
print_info "Vérification de la base de données..."
php artisan db:show 2>/dev/null || print_warning "Impossible de vérifier la base de données"

print_header "9. DÉSACTIVATION DU MODE MAINTENANCE"

print_info "Désactivation du mode maintenance..."
php artisan up
print_success "Application en ligne!"

print_header "10. POST-DÉPLOIEMENT"

# Redémarrer les queues
if command -v supervisorctl &> /dev/null; then
    print_info "Redémarrage des workers de queue..."
    supervisorctl restart all 2>/dev/null || print_warning "Supervisor non configuré"
else
    print_warning "Supervisor non installé - Pensez à redémarrer manuellement les workers"
    print_info "Commande: php artisan queue:restart"
fi

# Résumé
print_header "✅ DÉPLOIEMENT TERMINÉ"

echo ""
print_success "L'application est déployée avec succès!"
echo ""
print_info "Prochaines étapes recommandées:"
echo "  1. Vérifier les logs: tail -f storage/logs/laravel.log"
echo "  2. Tester l'API: curl $APP_URL/api/health"
echo "  3. Surveiller les erreurs pendant 1h"
echo "  4. Vérifier les connexions bancaires (Bridge)"
echo ""
print_warning "Rappels importants:"
echo "  • Les workers de queue doivent être actifs"
echo "  • Surveillez Sentry pour les erreurs"
echo "  • Vérifiez les backups automatiques"
echo ""

# URL de l'application
APP_URL=$(grep APP_URL .env | cut -d '=' -f2)
print_success "Application disponible sur: $APP_URL"

echo ""
print_info "Documentation: PRODUCTION_CHECKLIST.md"
echo ""
