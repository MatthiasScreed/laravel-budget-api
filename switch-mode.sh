#!/bin/bash

# ==========================================
# SCRIPT DE BASCULEMENT LOCAL <-> EXPOSE
# ==========================================
# Facilite le passage entre mode local et mode Expose
# Usage: ./switch-mode.sh [local|expose|status]

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
EXPOSE_SUBDOMAIN="coinquest-api"
EXPOSE_DOMAIN="sharedwithexpose.com"
LOCAL_DOMAIN="budget-api.test"

# Fonction d'affichage
log() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Fonction de vérification du fichier .env
check_env_exists() {
    if [ ! -f .env ]; then
        error "Fichier .env non trouvé !"
        error "Crée d'abord ton fichier .env"
        exit 1
    fi
}

# Fonction pour obtenir le mode actuel
get_current_mode() {
    if grep -q "APP_URL=.*sharedwithexpose.com" .env; then
        echo "expose"
    else
        echo "local"
    fi
}

# Fonction pour afficher le statut
show_status() {
    echo ""
    log "📊 Statut de la configuration"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    current_mode=$(get_current_mode)

    if [ "$current_mode" = "expose" ]; then
        success "Mode actuel: EXPOSE"
    else
        success "Mode actuel: LOCAL"
    fi

    echo ""
    log "Configuration actuelle:"
    echo "  APP_URL=$(grep "^APP_URL=" .env | cut -d'=' -f2)"
    echo "  SESSION_DOMAIN=$(grep "^SESSION_DOMAIN=" .env | cut -d'=' -f2)"
    echo "  EXPOSE_ENABLED=$(grep "^EXPOSE_ENABLED=" .env | cut -d'=' -f2)"

    bridge_callback=$(grep "^BRIDGE_CALLBACK_URL=" .env | grep -v "^#" | cut -d'=' -f2)
    if [ ! -z "$bridge_callback" ]; then
        echo "  BRIDGE_CALLBACK_URL=$bridge_callback"
    fi

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
}

# Fonction pour basculer en mode LOCAL
switch_to_local() {
    log "🏠 Passage en mode LOCAL..."

    check_env_exists

    # Backup
    cp .env .env.backup
    success "Backup créé: .env.backup"

    # Remplacer APP_URL
    sed -i.tmp 's|^APP_URL=.*|APP_URL=http://'"$LOCAL_DOMAIN"'|' .env

    # Remplacer SESSION_DOMAIN
    sed -i.tmp 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=localhost|' .env

    # Désactiver EXPOSE
    sed -i.tmp 's|^EXPOSE_ENABLED=.*|EXPOSE_ENABLED=false|' .env

    # Commenter les URLs Bridge Expose (ajouter # au début)
    sed -i.tmp 's|^BRIDGE_CALLBACK_URL=https://.*sharedwithexpose.com.*|#BRIDGE_CALLBACK_URL=https://'"$EXPOSE_SUBDOMAIN"'.'"$EXPOSE_DOMAIN"'/banking/callback|' .env
    sed -i.tmp 's|^BRIDGE_WEBHOOK_URL=https://.*sharedwithexpose.com.*|#BRIDGE_WEBHOOK_URL=https://'"$EXPOSE_SUBDOMAIN"'.'"$EXPOSE_DOMAIN"'/api/banking/webhook|' .env

    # Décommenter les URLs Bridge locales
    sed -i.tmp 's|^#BRIDGE_CALLBACK_URL=\${APP_URL}|BRIDGE_CALLBACK_URL=${APP_URL}|' .env
    sed -i.tmp 's|^#BRIDGE_WEBHOOK_URL=\${APP_URL}|BRIDGE_WEBHOOK_URL=${APP_URL}|' .env

    # Nettoyer les fichiers temporaires
    rm -f .env.tmp

    success "Configuration modifiée pour mode LOCAL"

    # Clear cache Laravel
    if command -v php &> /dev/null; then
        log "Nettoyage du cache Laravel..."
        php artisan config:clear > /dev/null 2>&1
        php artisan cache:clear > /dev/null 2>&1
        success "Cache Laravel nettoyé"
    fi

    echo ""
    warning "⚠️  N'oublie pas de :"
    echo "  1. Redémarrer ton serveur Laravel si nécessaire"
    echo "  2. Redémarrer ton frontend Vue.js"
    echo ""
}

# Fonction pour basculer en mode EXPOSE
switch_to_expose() {
    log "🌐 Passage en mode EXPOSE..."

    check_env_exists

    # Vérifier si Expose tourne
    if ! pgrep -f "expose" > /dev/null; then
        warning "Expose ne semble pas être lancé"
        echo "  Lance d'abord : ./start-expose.sh"
        echo ""
        read -p "Continuer quand même ? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            error "Opération annulée"
            exit 1
        fi
    fi

    # Backup
    cp .env .env.backup
    success "Backup créé: .env.backup"

    # Remplacer APP_URL
    sed -i.tmp 's|^APP_URL=.*|APP_URL=https://'"$EXPOSE_SUBDOMAIN"'.'"$EXPOSE_DOMAIN"'|' .env

    # Remplacer SESSION_DOMAIN
    sed -i.tmp 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=.'"$EXPOSE_DOMAIN"'|' .env

    # Activer EXPOSE
    sed -i.tmp 's|^EXPOSE_ENABLED=.*|EXPOSE_ENABLED=true|' .env

    # Commenter les URLs Bridge locales
    sed -i.tmp 's|^BRIDGE_CALLBACK_URL=\${APP_URL}|#BRIDGE_CALLBACK_URL=${APP_URL}|' .env
    sed -i.tmp 's|^BRIDGE_WEBHOOK_URL=\${APP_URL}|#BRIDGE_WEBHOOK_URL=${APP_URL}|' .env

    # Décommenter et mettre à jour les URLs Bridge Expose
    sed -i.tmp 's|^#BRIDGE_CALLBACK_URL=https://.*sharedwithexpose.com.*|BRIDGE_CALLBACK_URL=https://'"$EXPOSE_SUBDOMAIN"'.'"$EXPOSE_DOMAIN"'/banking/callback|' .env
    sed -i.tmp 's|^#BRIDGE_WEBHOOK_URL=https://.*sharedwithexpose.com.*|BRIDGE_WEBHOOK_URL=https://'"$EXPOSE_SUBDOMAIN"'.'"$EXPOSE_DOMAIN"'/api/banking/webhook|' .env

    # Si les lignes n'existent pas, les ajouter
    if ! grep -q "BRIDGE_CALLBACK_URL=https://" .env; then
        echo "BRIDGE_CALLBACK_URL=https://$EXPOSE_SUBDOMAIN.$EXPOSE_DOMAIN/banking/callback" >> .env
    fi

    if ! grep -q "BRIDGE_WEBHOOK_URL=https://" .env; then
        echo "BRIDGE_WEBHOOK_URL=https://$EXPOSE_SUBDOMAIN.$EXPOSE_DOMAIN/api/banking/webhook" >> .env
    fi

    # Nettoyer les fichiers temporaires
    rm -f .env.tmp

    success "Configuration modifiée pour mode EXPOSE"

    # Clear cache Laravel
    if command -v php &> /dev/null; then
        log "Nettoyage du cache Laravel..."
        php artisan config:clear > /dev/null 2>&1
        php artisan cache:clear > /dev/null 2>&1
        success "Cache Laravel nettoyé"
    fi

    echo ""
    warning "⚠️  N'oublie pas de :"
    echo "  1. Vérifier qu'Expose tourne : ./start-expose.sh"
    echo "  2. Configurer Bridge Console avec l'URL :"
    echo "     https://$EXPOSE_SUBDOMAIN.$EXPOSE_DOMAIN"
    echo "  3. Redémarrer ton serveur Laravel si nécessaire"
    echo "  4. Redémarrer ton frontend Vue.js"
    echo ""
}

# Menu principal
show_menu() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  🔄 Basculement MODE - CoinQuest API"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "1) 🏠 Passer en mode LOCAL"
    echo "2) 🌐 Passer en mode EXPOSE"
    echo "3) 📊 Afficher le statut"
    echo "4) ↩️  Restaurer backup"
    echo "5) ❌ Quitter"
    echo ""
    read -p "Choix (1-5): " choice

    case $choice in
        1)
            switch_to_local
            show_status
            ;;
        2)
            switch_to_expose
            show_status
            ;;
        3)
            show_status
            ;;
        4)
            if [ -f .env.backup ]; then
                cp .env.backup .env
                success "Backup restauré"
                show_status
            else
                error "Aucun backup trouvé"
            fi
            ;;
        5)
            log "Au revoir !"
            exit 0
            ;;
        *)
            error "Choix invalide"
            show_menu
            ;;
    esac
}

# Point d'entrée principal
main() {
    # Si argument fourni
    if [ $# -gt 0 ]; then
        case $1 in
            local)
                switch_to_local
                show_status
                ;;
            expose)
                switch_to_expose
                show_status
                ;;
            status)
                show_status
                ;;
            *)
                error "Argument invalide: $1"
                echo "Usage: $0 [local|expose|status]"
                exit 1
                ;;
        esac
    else
        # Mode interactif
        show_status
        show_menu
    fi
}

# Exécution
main "$@"
