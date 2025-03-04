<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Repository;

use App\Entity\Alunno;
use App\Entity\AssenzaLezione;
use App\Entity\Classe;
use App\Entity\Lezione;
use App\Entity\ModuloFormativo;
use App\Entity\ScansioneOraria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * ModuloFormativo - repository
 *
 * @author Antonello Dessì
 */
class ModuloFormativoRepository extends ServiceEntityRepository {

  /**
   * Costruttore
   *
   * @param TranslatorInterface $trans Gestore delle traduzioni
   */
  public function __construct(
    private readonly ManagerRegistry $registry,
    private readonly TranslatorInterface $trans)
  {
    parent::__construct($registry, ModuloFormativo::class);
  }

  /**
   * Restituisce la lista dei moduli formativi configurati
   *
   * @param int $anno Anno di corso della classe
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioni(int $anno): array {
    // inizializza
    $dati = [];
    // legge moduli formativi
    $moduliFormativi = $this->createQueryBuilder('m')
      ->orderBy('m.tipo,m.nomeBreve')
      ->getQuery()
      ->getResult();
    // imposta opzioni
    foreach ($moduliFormativi as $moduloFormativo) {
      if (in_array($anno, $moduloFormativo->getClassi())) {
        $tipo = $this->trans->trans('label.modulo_formativo_tipo_'.$moduloFormativo->getTipo());
        $dati[$tipo.': '.$moduloFormativo->getNomeBreve()] = $moduloFormativo;
      }
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista dei tipi di modulo formativo utilizzati per la classe
   *
   * @param Classe $classe Classe da considerare nella ricerca
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioniTipiClasse(Classe $classe): array {
    // inizializza
    $dati = [];
    // legge tipi
    $tipi = $this->createQueryBuilder('mf')
      ->select('DISTINCT mf.tipo')
      ->join(Lezione::class, 'l', 'WITH', 'l.moduloFormativo=mf.id')
      ->join('l.classe', 'c')
      ->where("c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)")
      ->orderBy('mf.tipo')
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('gruppo', $classe->getGruppo())
      ->getQuery()
      ->getSingleColumnResult();
    // imposta opzioni
    foreach ($tipi as $tipo) {
      $nomeTipo = $this->trans->trans('label.modulo_formativo_tipo_'.$tipo);
      $dati[$nomeTipo] = $tipo;
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista dei moduli formativi utilizzati per la classe
   *
   * @param Classe $classe Classe da considerare nella ricerca
   *
   * @return array Array associativo predisposto per le opzioni dei form
   */
  public function opzioniModuliClasse(Classe $classe): array {
    // inizializza
    $dati = [];
    // legge tipi
    $moduli = $this->createQueryBuilder('mf')
      ->select('DISTINCT mf.id,mf.nomeBreve,mf.tipo')
      ->join(Lezione::class, 'l', 'WITH', 'l.moduloFormativo=mf.id')
      ->join('l.classe', 'c')
      ->where("c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL)")
      ->orderBy('mf.tipo,mf.nomeBreve')
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('gruppo', $classe->getGruppo())
      ->getQuery()
      ->getArrayResult();
    // imposta opzioni
    foreach ($moduli as $modulo) {
      $dati[$modulo['tipo']][$modulo['nomeBreve']] = $modulo['id'];
    }
    // restituisce lista opzioni
    return $dati;
  }

  /**
   * Restituisce la lista dei moduli formativi secondo i criteri di ricerca indicati
   *
   * @param array $criteri Lista dei criteri di ricerca
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function cerca(array $criteri): array {
    // inizializza
    $dati = [];
    // legge dati
    $moduli = $this->createQueryBuilder('mf')
      ->select('l.data,l.argomento,l.attivita,l.id AS lezione_id,so.inizio,so.fine,so.durata,mf.id,mf.nome AS modulo,mf.nomeBreve AS moduloBreve,mf.tipo,m.nome AS materia,m.nomeBreve AS materiaBreve')
      ->join(Lezione::class, 'l', 'WITH', 'l.moduloFormativo=mf.id')
      ->join('l.classe', 'c')
      ->join('l.materia', 'm')
      ->join(ScansioneOraria::class, 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
      ->join('so.orario', 'o')
      ->where("c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL) AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede");
    if ($criteri['tipo']) {
      $moduli->andwhere('mf.tipo=:tipo')->setParameter('tipo', $criteri['tipo']);
    }
    if ($criteri['moduloFormativo']) {
      $moduli->andwhere('mf.id=:modulo')->setParameter('modulo', $criteri['moduloFormativo']);
    }
    $moduli = $moduli
      ->orderBy('l.data,so.inizio')
      ->setParameter('anno', $criteri['classe']->getAnno())
      ->setParameter('sezione', $criteri['classe']->getSezione())
      ->setParameter('gruppo', $criteri['classe']->getGruppo())
      ->setParameter('sede', $criteri['sede'])
      ->getQuery()
      ->getArrayResult();
    $dati['ore'] = 0;
    foreach ($moduli as $modulo) {
      $dati['ore'] += $modulo['durata'];
    }
    $dati['lista'] = $moduli;
    // restituisce dati
    return $dati;
  }

  /**
   * Restituisce i moduli formativi svolti per gli alunni della classe indicata
   *
   * @param Classe $classe Classe di cui leggere la situazione dei moduli formativi svolti
   *
   * @return array Array associativo con i risultati della ricerca
   */
  public function alunni(Classe $classe): array {
    // inizializza
    $dati = [];
    // legge alunni
    $alunni = $this->getEntityManager()->getRepository(Alunno::class)->classe($classe);
    foreach ($alunni as $alunno) {
      $dati['alunni'][$alunno['id']] = $alunno;
      $dati['alunni'][$alunno['id']]['O']['ore'] = 0;
      $dati['alunni'][$alunno['id']]['O']['moduli'] = [];
      $dati['alunni'][$alunno['id']]['P']['ore'] = 0;
      $dati['alunni'][$alunno['id']]['P']['moduli'] = [];
    }
    // legge moduli formativi
    $moduli = $this->createQueryBuilder('mf')
      ->select('l.data,l.id AS lezione_id,so.inizio,so.fine,so.durata,mf.id,mf.nome AS modulo,mf.nomeBreve AS moduloBreve,mf.tipo')
      ->join(Lezione::class, 'l', 'WITH', 'l.moduloFormativo=mf.id')
      ->join('l.classe', 'c')
      ->join(ScansioneOraria::class, 'so', 'WITH', 'l.ora=so.ora AND (WEEKDAY(l.data)+1)=so.giorno')
      ->join('so.orario', 'o')
      ->where("c.anno=:anno AND c.sezione=:sezione AND (c.gruppo=:gruppo OR c.gruppo='' OR c.gruppo IS NULL) AND l.data BETWEEN o.inizio AND o.fine AND o.sede=:sede")
      ->orderBy('l.data,so.inizio')
      ->setParameter('anno', $classe->getAnno())
      ->setParameter('sezione', $classe->getSezione())
      ->setParameter('gruppo', $classe->getGruppo())
      ->setParameter('sede', $classe->getSede())
      ->getQuery()
      ->getArrayResult();
    // calcola ore svolte senza assenze
    foreach ($moduli as $modulo) {
      $dati['moduli'][$modulo['lezione_id']] = $modulo;
      $assenti = $this->getEntityManager()->getRepository(AssenzaLezione::class)->createQueryBuilder('al')
        ->select('(al.alunno) AS alunno')
        ->where('al.lezione=:lezione')
        ->setParameter('lezione', $modulo['lezione_id'])
        ->getQuery()
        ->getSingleColumnResult();
      $presenti = array_diff(array_keys($dati['alunni']), $assenti);
      foreach ($presenti as $presente) {
        $dati['alunni'][$presente][$modulo['tipo']]['ore'] += $modulo['durata'];
        $dati['alunni'][$presente][$modulo['tipo']]['moduli'][] = $modulo['lezione_id'];
      }
    }
    // restituisce dati
    return $dati;
  }

}
