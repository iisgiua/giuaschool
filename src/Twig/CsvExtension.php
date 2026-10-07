<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * CsvExtension - Fornisce il filtro Twig "csv" per l'escaping sicuro dei valori CSV.
 *
 * Il filtro:
 *  - neutralizza le formule (CSV injection) prefissando con apice singolo i valori
 *    che iniziano con =, +, -, @, TAB o CR;
 *  - applica il quoting CSV raddoppiando le virgolette doppie;
 *  - rimuove CR e LF sostituendoli con spazio, per non spezzare le righe;
 *  - racchiude sempre il valore tra virgolette doppie.
 *
 * @author Antonello Dessì
 */
class CsvExtension extends AbstractExtension
{
    /**
     * Restituisce i filtri forniti dall'estensione.
     *
     * @return array Lista dei filtri Twig
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('csv', [$this, 'csvEscape'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Effettua l'escaping di un valore per l'inserimento in un file CSV.
     *
     * @param mixed $value Valore da convertire
     *
     * @return string Valore escapato, pronto per il CSV
     */
    public function csvEscape($value): string
    {
        $v = (string) ($value ?? '');

        // guardia CSV injection: prefissa con apice singolo i valori pericolosi
        if ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v)) {
            $v = "'".$v;
        }

        // quoting CSV: raddoppia le virgolette, sostituisce CR/LF con spazio
        $v = str_replace(['"', "\r", "\n"], ['""', ' ', ' '], $v);

        // racchiude sempre tra virgolette
        return '"'.$v.'"';
    }
}
