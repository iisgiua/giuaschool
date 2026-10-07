<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use App\Util\ConfigLoader;

/**
 * SelettoreClasseExtension - fornisce le funzioni Twig "gs_classi_selettore"
 * e "gs_scelta_selettore".
 *
 * Restituisce l'elenco condiviso (in sessione) delle classi usato dal
 * componente "selettore-classe.html.twig". Il dato viene caricato una sola
 * volta e cachato in sessione: se manca (sessioni aperte prima della modifica)
 * viene caricato al primo utilizzo, senza dipendere dal login.
 *
 * @author Antonello Dessì
 */
class SelettoreClasseExtension extends AbstractExtension
{
    /**
     * Costruttore
     *
     * @param ConfigLoader $config Gestore della configurazione su database
     */
    public function __construct(private readonly ConfigLoader $config)
    {
    }

    /**
     * Restituisce le funzioni fornite dall'estensione.
     *
     * @return TwigFunction[] Lista delle funzioni Twig
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('gs_classi_selettore', [$this, 'classiSelettore']),
            new TwigFunction('gs_scelta_selettore', [$this, 'sceltaSelettore']),
        ];
    }

    /**
     * Elenco delle classi per il selettore sede/classe.
     *
     * @return array Elenco delle classi [{id, anno, sezione, gruppo, sede, propria}]
     */
    public function classiSelettore(): array
    {
        return $this->config->classiSelettore();
    }

    /**
     * Scelta corrente del selettore sede/classe (stato attivo).
     *
     * @return array Scelta in sessione [{cattedra, classe}]
     */
    public function sceltaSelettore(): array
    {
        return $this->config->sceltaSelettore();
    }
}
