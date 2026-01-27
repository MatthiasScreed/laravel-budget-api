# 📦 Guide d'activation du système de Projets CoinQuest

**Date:** 2026-01-27
**Version:** 1.0.0
**Statut:** ✅ SYSTÈME ACTIVÉ ET PRÊT

---

## 🎯 Vue d'ensemble

Le système de **Projets** (Projects) permet aux utilisateurs de gérer des projets financiers complexes avec des templates préconf igurés. Un projet est une combinaison d'un objectif financier (`financial_goals`) avec des catégories, milestones et tips personnalisés.

### Cas d'usage

- 🛫 **Voyage** - Vacances, tour du monde
- 🏠 **Immobilier** - Apport, frais de notaire
- 🚗 **Voiture** - Achat véhicule neuf/occasion
- 🎓 **Éducation** - Formation, études
- 💼 **Business** - Création d'entreprise
- 🛡️ **Fonds d'urgence** - Réserve de sécurité
- 🎉 **Événements** - Mariage, anniversaire
- 🔨 **Travaux** - Rénovation maison
- 💳 **Dette** - Remboursement accéléré
- 📈 **Investissement** - Capital d'investissement

---

## ✅ CE QUI A ÉTÉ FAIT

### 1. Base de données

✅ **Tables créées** (migrations existantes):
- `project_templates` - Templates prédéfinis
- `user_projects` - Projets des utilisateurs

✅ **Seeder créé et exécuté**:
```bash
php artisan db:seed --class=ProjectTemplateSeeder
```
→ 10 templates de projets insérés dans la base

### 2. Models

✅ **Models créés**:
- `App\Models\ProjectTemplate` - Model pour les templates
- `App\Models\UserProject` - Model pour les projets utilisateurs

### 3. Routes API

✅ **Routes ajoutées** dans `routes/api.php`:
```php
// GET /api/projects/templates - Liste des templates
// GET /api/projects - Liste des projets de l'utilisateur
// POST /api/projects - Créer un projet
// GET /api/projects/{id} - Détails d'un projet
// PUT /api/projects/{id} - Modifier un projet
// DELETE /api/projects/{id} - Supprimer un projet
// POST /api/projects/from-template - Créer depuis un template
// POST /api/projects/{id}/start - Démarrer un projet
// POST /api/projects/{id}/pause - Mettre en pause
// POST /api/projects/{id}/complete - Marquer comme terminé
// POST /api/projects/{id}/cancel - Annuler
// GET /api/projects/{id}/milestones - Liste des étapes
// POST /api/projects/{id}/milestones/{milestone}/complete - Compléter une étape
```

### 4. Controller

✅ **Controller existant**: `App\Http\Controllers\Api\ProjectController`
- Méthodes déjà implémentées:
  - `getTemplates()` - Récupérer les templates
  - `createFromTemplate()` - Créer projet depuis template
  - Méthodes du dashboard inclues

⚠️ **Méthodes manquantes à implémenter**:
- `index()` - Lister les projets
- `store()` - Créer projet
- `show()` - Afficher projet
- `update()` - Modifier projet
- `destroy()` - Supprimer projet
- `start()`, `pause()`, `complete()`, `cancel()` - Gestion du statut
- `milestones()`, `completeMilestone()` - Gestion des étapes

### 5. Service

✅ **Service existant**: `App\Services\ProjectService`
- Tous les templates implémentés
- Logique métier complète

---

## 📋 TEMPLATES DISPONIBLES

| Key | Nom | Type | Popularité | Premium |
|-----|-----|------|-----------|---------|
| `travel` | Voyage | purchase | 85 | Non |
| `emergency_fund` | Fonds d'Urgence | emergency_fund | 78 | Non |
| `car` | Achat Voiture | purchase | 72 | Non |
| `real_estate` | Achat Immobilier | investment | 65 | Non |
| `event` | Événement Spécial | purchase | 58 | Non |
| `home_improvement` | Travaux Maison | purchase | 45 | Non |
| `education` | Formation/Éducation | investment | 42 | Non |
| `investment` | Investissement | investment | 38 | **Oui** |
| `debt_payoff` | Remboursement Dette | debt_payoff | 35 | Non |
| `business` | Création Entreprise | investment | 28 | **Oui** |

---

## 🚀 UTILISATION DE L'API

### 1. Récupérer les templates

```bash
GET /api/projects/templates
Authorization: Bearer {token}
```

**Réponse:**
```json
{
  "success": true,
  "data": {
    "templates": [
      {
        "key": "travel",
        "name": "Voyage",
        "description": "Planifier et budgétiser un voyage",
        "icon": "airplane",
        "color": "#10B981",
        "type": "purchase",
        "categories": [...],
        "default_duration_months": 12,
        "tips": [...],
        "min_amount": 500,
        "max_amount": 50000,
        "popularity_score": 85,
        "is_premium": false
      },
      ...
    ],
    "popular": [...],
    "categories": {
      "popular": ["travel", "emergency_fund", "car"],
      "long_term": ["real_estate", "investment", "education"],
      "lifestyle": ["event", "home_improvement"],
      "business": ["business", "debt_payoff"]
    }
  }
}
```

### 2. Créer un projet depuis un template

```bash
POST /api/projects/from-template
Authorization: Bearer {token}
Content-Type: application/json

{
  "template_type": "travel",
  "name": "Voyage au Japon 2026",
  "description": "Découverte du Japon pendant 3 semaines",
  "target_amount": 5000,
  "target_date": "2026-08-15"
}
```

**Réponse:**
```json
{
  "success": true,
  "message": "Projet créé avec succès !",
  "data": {
    "goal": {
      "id": 42,
      "name": "Voyage au Japon 2026",
      "target_amount": 5000,
      "current_amount": 0,
      "progress_percentage": 0,
      "target_date": "2026-08-15"
    },
    "categories": [...],
    "milestones": [
      {
        "percentage": 25,
        "amount": 1250,
        "description": "Premier quart de votre Voyage atteint !"
      },
      ...
    ],
    "suggestions": [
      "Réservez vos billets d'avion 2-3 mois à l'avance",
      ...
    ]
  },
  "gaming": {
    "xp_gained": 50,
    "new_level": 5,
    "achievements_unlocked": []
  }
}
```

---

## 🔧 INTÉGRATION FRONTEND

### 1. Service TypeScript

Créez `src/services/projectService.ts`:

```typescript
import { apiClient } from './api'

export interface ProjectTemplate {
  key: string
  name: string
  description: string
  icon: string
  color: string
  type: string
  categories: Array<{
    name: string
    percentage: number
    icon: string
  }>
  default_duration_months: number
  tips: string[]
  milestones?: Array<{
    percentage: number
    description: string
  }>
  min_amount: number
  max_amount: number
  popularity_score: number
  is_premium: boolean
}

export interface CreateProjectRequest {
  template_type: string
  name: string
  description?: string
  target_amount: number
  target_date: string
}

export const projectService = {
  // Récupérer tous les templates
  async getTemplates() {
    const response = await apiClient.get('/projects/templates')
    return response.data
  },

  // Créer un projet depuis un template
  async createFromTemplate(data: CreateProjectRequest) {
    const response = await apiClient.post('/projects/from-template', data)
    return response.data
  },

  // Liste des projets de l'utilisateur
  async getUserProjects() {
    const response = await apiClient.get('/projects')
    return response.data
  },

  // Détails d'un projet
  async getProject(id: number) {
    const response = await apiClient.get(`/projects/${id}`)
    return response.data
  },

  // Modifier un projet
  async updateProject(id: number, data: Partial<CreateProjectRequest>) {
    const response = await apiClient.put(`/projects/${id}`, data)
    return response.data
  },

  // Supprimer un projet
  async deleteProject(id: number) {
    const response = await apiClient.delete(`/projects/${id}`)
    return response.data
  },

  // Actions sur le projet
  async startProject(id: number) {
    const response = await apiClient.post(`/projects/${id}/start`)
    return response.data
  },

  async pauseProject(id: number) {
    const response = await apiClient.post(`/projects/${id}/pause`)
    return response.data
  },

  async completeProject(id: number) {
    const response = await apiClient.post(`/projects/${id}/complete`)
    return response.data
  }
}
```

### 2. Store Zustand

Créez `src/stores/projectStore.ts`:

```typescript
import { create } from 'zustand'
import { projectService, ProjectTemplate } from '@/services/projectService'

interface ProjectStore {
  templates: ProjectTemplate[]
  loading: boolean
  error: string | null

  fetchTemplates: () => Promise<void>
  createProject: (data: CreateProjectRequest) => Promise<any>
}

export const useProjectStore = create<ProjectStore>((set) => ({
  templates: [],
  loading: false,
  error: null,

  fetchTemplates: async () => {
    set({ loading: true, error: null })
    try {
      const data = await projectService.getTemplates()
      set({ templates: data.data.templates, loading: false })
    } catch (error) {
      set({ error: error.message, loading: false })
    }
  },

  createProject: async (data) => {
    set({ loading: true, error: null })
    try {
      const result = await projectService.createFromTemplate(data)
      set({ loading: false })
      return result
    } catch (error) {
      set({ error: error.message, loading: false })
      throw error
    }
  }
}))
```

### 3. Composant React/Vue

```typescript
// React example
import { useEffect } from 'react'
import { useProjectStore } from '@/stores/projectStore'

export function ProjectTemplates() {
  const { templates, loading, fetchTemplates } = useProjectStore()

  useEffect(() => {
    fetchTemplates()
  }, [])

  if (loading) return <div>Chargement...</div>

  return (
    <div className="grid grid-cols-3 gap-4">
      {templates.map(template => (
        <div
          key={template.key}
          className="p-4 border rounded-lg"
          style={{ borderColor: template.color }}
        >
          <div className="flex items-center gap-2">
            <span className="text-2xl">{template.icon}</span>
            <h3 className="font-bold">{template.name}</h3>
          </div>
          <p className="text-sm text-gray-600 mt-2">
            {template.description}
          </p>
          <div className="mt-4">
            <span className="text-xs bg-gray-100 px-2 py-1 rounded">
              {template.type}
            </span>
            {template.is_premium && (
              <span className="text-xs bg-yellow-100 px-2 py-1 rounded ml-2">
                Premium
              </span>
            )}
          </div>
          <button
            onClick={() => handleCreateProject(template)}
            className="mt-4 w-full bg-blue-500 text-white px-4 py-2 rounded"
          >
            Utiliser ce template
          </button>
        </div>
      ))}
    </div>
  )
}
```

---

## 📝 TÂCHES RESTANTES (Pour le frontend)

1. **Créer les composants UI**
   - Page liste des templates
   - Modal de création de projet
   - Page détails d'un projet
   - Widget milestones

2. **Créer les vues/pages**
   - `/projects` - Liste des projets
   - `/projects/templates` - Galerie de templates
   - `/projects/create` - Créer un projet
   - `/projects/:id` - Détails d'un projet

3. **Ajouter les fonctionnalités**
   - Création guidée depuis template
   - Suivi des étapes (milestones)
   - Visualisation du progrès
   - Suggestions personnalisées

4. **Design**
   - Cards de templates avec couleurs
   - Icônes pour chaque type
   - Progrès bars
   - Badges premium

---

## 🧪 TESTS API

```bash
# 1. S'authentifier
POST http://localhost/api/auth/login
{
  "email": "demo@budget-gaming.com",
  "password": "password"
}

# 2. Récupérer les templates
GET http://localhost/api/projects/templates
Authorization: Bearer {token}

# 3. Créer un projet
POST http://localhost/api/projects/from-template
Authorization: Bearer {token}
{
  "template_type": "travel",
  "name": "Mon voyage de rêve",
  "target_amount": 3000,
  "target_date": "2026-12-31"
}
```

---

## 💡 CONSEILS D'IMPLÉMENTATION

### Backend (déjà fait)
- ✅ Migrations exécutées
- ✅ Models créés
- ✅ Routes configurées
- ✅ Service implémenté
- ✅ Seeder créé et exécuté

### Frontend (à faire)
1. Activez le service : `projectService.ts`
2. Créez le store : `projectStore.ts`
3. Créez les pages/composants
4. Testez avec l'API

---

## 🎯 EXEMPLE DE WORKFLOW UTILISATEUR

1. **Découverte**
   - L'utilisateur accède à `/projects/templates`
   - Voit les 10 templates disponibles triés par popularité
   - Peut filtrer par type (purchase, investment, etc.)

2. **Sélection**
   - Clique sur "Voyage"
   - Voit les détails : catégories, tips, budget recommandé

3. **Configuration**
   - Remplit le formulaire :
     - Nom : "Vacances été 2026"
     - Montant : 4000€
     - Date : 15/08/2026
   - Le système génère automatiquement :
     - Objectif financier lié
     - 5 catégories prédéfinies (Transport, Hébergement, etc.)
     - 4 milestones (25%, 50%, 75%, 100%)
     - Tips personnalisés

4. **Suivi**
   - Dashboard projet avec progrès
   - Milestones visuels
   - Suggestions d'optimisation
   - +50 XP pour création du projet

---

## 📊 STATISTIQUES

- **10 templates** disponibles
- **2 templates premium** (Business, Investment)
- **50+ XP** pour création d'un projet
- **Types de projets**: purchase, investment, emergency_fund, debt_payoff
- **Durées moyennes**: 12-36 mois
- **Budget min/max**: 500€ - 500 000€

---

## 🔗 RESSOURCES

- **Models**: `app/Models/ProjectTemplate.php`, `app/Models/UserProject.php`
- **Controller**: `app/Http/Controllers/Api/ProjectController.php`
- **Service**: `app/Services/ProjectService.php`
- **Routes**: `routes/api.php` (ligne 207-234)
- **Migrations**: `database/migrations/2025_05_28_*_create_project*.php`
- **Seeder**: `database/seeders/ProjectTemplateSeeder.php`

---

## ✅ CHECKLIST FINALE

- [x] Migrations créées
- [x] Models créés
- [x] Routes API ajoutées
- [x] Seeder créé et exécuté
- [x] Service implémenté
- [x] 10 templates insérés
- [ ] Frontend service créé
- [ ] Frontend store créé
- [ ] Composants UI créés
- [ ] Pages créées
- [ ] Tests frontend

---

**Version:** 1.0.0
**Dernière mise à jour:** 2026-01-27
**Statut:** ✅ Backend prêt, Frontend à implémenter

Pour toute question, référez-vous à ce guide ou consultez le code source dans `app/Services/ProjectService.php`.
