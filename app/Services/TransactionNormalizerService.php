<?php

namespace App\Services;

/**
 * Service de normalisation des libellés bancaires
 *
 * Nettoie et standardise les descriptions de transactions
 * pour améliorer la précision de la catégorisation
 */
class TransactionNormalizerService
{
    /**
     * Mots à supprimer (stopwords bancaires)
     */
    private array $stopwords = [
        'PAIEMENT', 'PAR', 'CARTE', 'VIREMENT', 'PRELEVEMENT',
        'SEPA', 'ECHEANCE', 'MENSUALITE', 'ACHAT', 'RETRAIT',
        'DEPOT', 'ESPECES', 'CHEQUE', 'OPERATION', 'FRAIS',
    ];

    /**
     * Préfixes bancaires à supprimer
     */
    private array $bankPrefixes = [
        'CB\s*\*+\d+',           // CB*1234
        'CARTE\s+X+\d{4}',       // CARTE X1234
        'N°?\s*\d{6,}',          // N°123456
        'REF\s*:\s*\d+',         // REF: 123
        'VIR\s+SEPA',            // VIR SEPA
        'PRLV\s+SEPA',           // PRLV SEPA
    ];

    /**
     * Normaliser un libellé de transaction
     *
     * @param  string  $label  Libellé brut de la transaction
     * @return string Libellé nettoyé et standardisé
     */
    public function normalize(string $label): string
    {
        // Conversion en majuscules
        $label = mb_strtoupper($label, 'UTF-8');

        // Supprimer dates (formats: DD/MM/YYYY, DD-MM-YY, etc.)
        $label = $this->removeDates($label);

        // Supprimer heures (HH:MM, HH:MM:SS)
        $label = $this->removeTimes($label);

        // Supprimer références bancaires
        $label = $this->removeBankReferences($label);

        // Supprimer stopwords
        $label = $this->removeStopwords($label);

        // Nettoyer caractères spéciaux
        $label = $this->cleanSpecialChars($label);

        // Nettoyer espaces multiples et trim
        $label = $this->cleanWhitespace($label);

        return $label;
    }

    /**
     * Extraire le nom du commerçant
     *
     * @param  string  $label  Libellé de transaction
     * @return string|null Nom du commerçant extrait
     */
    public function extractMerchant(string $label): ?string
    {
        $normalized = $this->normalize($label);

        // Extraire les premiers mots (généralement = nom commerçant)
        preg_match('/^([A-Z\s]{3,30})/', $normalized, $matches);

        $merchant = $matches[1] ?? null;

        if (! $merchant || strlen($merchant) < 3) {
            return null;
        }

        return trim($merchant);
    }

    /**
     * Supprimer dates du libellé
     */
    protected function removeDates(string $text): string
    {
        $patterns = [
            '/\d{2}[\/\-\.]\d{2}[\/\-\.]\d{2,4}/', // DD/MM/YYYY
            '/\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2}/',   // YYYY/MM/DD
            '/\d{2}\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+\d{2,4}/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }

        return $text;
    }

    /**
     * Supprimer heures du libellé
     */
    protected function removeTimes(string $text): string
    {
        return preg_replace('/\d{2}:\d{2}(:\d{2})?/', '', $text);
    }

    /**
     * Supprimer références bancaires
     */
    protected function removeBankReferences(string $text): string
    {
        foreach ($this->bankPrefixes as $pattern) {
            $text = preg_replace('/'.$pattern.'/', '', $text);
        }

        return $text;
    }

    /**
     * Supprimer stopwords bancaires
     */
    protected function removeStopwords(string $text): string
    {
        return str_ireplace($this->stopwords, '', $text);
    }

    /**
     * Nettoyer caractères spéciaux
     */
    protected function cleanSpecialChars(string $text): string
    {
        // Garder lettres, chiffres, espaces, & et '
        return preg_replace('/[^A-Z0-9\s&\']/u', ' ', $text);
    }

    /**
     * Nettoyer espaces multiples
     */
    protected function cleanWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Détecter si le libellé contient un montant
     */
    public function containsAmount(string $label): bool
    {
        return (bool) preg_match('/\d+[,\.]\d{2}/', $label);
    }

    /**
     * Extraire montant du libellé (si présent)
     */
    public function extractAmount(string $label): ?float
    {
        preg_match('/(\d+)[,\.](\d{2})/', $label, $matches);

        if (isset($matches[0])) {
            $amount = str_replace(',', '.', $matches[0]);

            return (float) $amount;
        }

        return null;
    }

    /**
     * Détecter si récurrent basé sur le libellé
     */
    public function isRecurringLabel(string $label): bool
    {
        $recurringKeywords = [
            'MENSUALITE', 'ABONNEMENT', 'ECHEANCE',
            'SUBSCRIPTION', 'RECURRING', 'MONTHLY',
        ];

        $upper = mb_strtoupper($label);

        foreach ($recurringKeywords as $keyword) {
            if (str_contains($upper, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
