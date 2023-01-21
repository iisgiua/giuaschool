<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Alunno;
use App\Entity\Docente;
use App\Entity\Genitore;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * ModuloType - form per varie procedure senza entità di riferimento
 *
 * @author Antonello Dessì
 */
class ModuloType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'nuovo') {
      // form nuovo anno
      $builder
        ->setAction($options['actionUrl']);
      if ($options['step'] <= 4) {
        $builder
          ->add('submit', SubmitType::class, array('label' => $options['step'] == 0 ? 'label.start' : 'label.next'));
      } else {
        $builder
          ->add('submit', SubmitType::class, array('label' => 'label.submit',
            'attr' => ['style' => 'visibility:hidden']));
      }
    } elseif ($options['formMode'] == 'archivia') {
      // form archivia
      $builder
        ->add('docente', ChoiceType::class, array('label' => 'label.registro_docente',
          'choices' => array_merge([-1], $options['dati'][0]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
                $options['dati'][3]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('sostegno', ChoiceType::class, array('label' => 'label.registro_sostegno',
          'choices' => array_merge([-1], $options['dati'][1]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
                $options['dati'][3]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('classe', ChoiceType::class, array('label' => 'label.registro_classe',
          'choices' => array_merge([-1], $options['dati'][2]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
                $options['dati'][4]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'group_by' => function ($obj) {
              return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('scrutinio', ChoiceType::class, array('label' => 'label.documenti_scrutinio',
          'choices' => array_merge([-1], $options['dati'][2]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
                $options['dati'][4]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'group_by' => function ($obj) {
              return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('circolare', ChoiceType::class, array('label' => 'label.archivio_circolari',
          'choices' => ['label.no' => false, 'label.si' => true],
          'required' => true));
    } elseif ($options['formMode'] == 'staff') {
      $builder
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['dati'][0],
          'class' => 'App\Entity\Docente',
          'choice_label' => function ($obj) {
              return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'placeholder' => 'label.choose_option',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
              ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'attr' => ['widget' => 'search'],
          'disabled' => ($options['dati'][0] != null),
          'required' => true))
        ->add('sede', EntityType::class, array('label' => 'label.sede',
          'data' => $options['dati'][1],
          'class' => 'App\Entity\Sede',
          'choice_label' => 'citta',
          'placeholder' => 'label.qualsiasi_sede',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.ordinamento', 'ASC'); },
          'required' => false));
    } elseif ($options['formMode'] == 'coordinatori') {
      $builder
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['dati'][0],
          'class' => 'App\Entity\Classe',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC'); },
          'choice_label' => function ($obj, $val) {
              return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => function ($obj) {
              return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'disabled' => ($options['dati'][0] != null),
          'required' => true))
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['dati'][1],
          'class' => 'App\Entity\Docente',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
              ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'choice_label' => function ($obj) {
              return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true));
    } elseif ($options['formMode'] == 'log') {
      $builder
        ->add('data', DateType::class, array('label' => 'label.data',
          'data' => $options['dati'][0],
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true))
        ->add('ora', TimeType::class, array('label' => 'label.ora_inizio',
          'data' => $options['dati'][1],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true));
    } elseif ($options['formMode'] == 'email') {
      // form configurazione email
      $builder
        ->add('server', ChoiceType::class, array('label' => 'label.mailserver_tipo',
          'data' => $options['dati']['server'],
          'choices' => ['label.mailserver_smtp' => 'smtp', 'label.mailserver_sendmail' => 'sendmail',
            'label.mailserver_gmail' => 'gmail+smtp', 'label.mailserver_php' => 'php'],
          'help' => 'message.mailserver_help_smtp',
          'required' => true))
        ->add('user', TextType::class, array('label' => 'label.mailserver_user',
          'data' => $options['dati']['user'],
          'attr' => ['widget' => 'gs-row-start'],
          'required' => false))
        ->add('password', PasswordType::class, array('label' => 'label.mailserver_password',
          'data' => $options['dati']['password'],
          'always_empty' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => false))
        ->add('host', TextType::class, array('label' => 'label.mailserver_host',
          'data' => $options['dati']['host'],
          'attr' => ['widget' => 'gs-row-start'],
          'required' => false))
        ->add('port', IntegerType::class, array('label' => 'label.mailserver_port',
          'data' => $options['dati']['port'],
          'attr' => ['widget' => 'gs-row-end'],
          'required' => false))
        ->add('email', EmailType::class, array('label' => 'label.mailserver_email',
          'required' => true));
      } elseif ($options['formMode'] == 'rappresentanti') {
        // form rappresentanti
        $builder
          ->add('utente', ChoiceType::class, array('label' => 'label.utente',
            'data' => $options['dati'][0],
            'choices' => $options['dati'][1],
            'choice_label' => function ($obj) {
              return ($obj instanceOf Alunno ? $obj.' - '.$obj->getClasse() :
                ($obj instanceOf Genitore ? $obj.' - '.$obj->getAlunno().' - '.$obj->getAlunno()->getClasse() :
                ($obj instanceOf Docente ? $obj.' ('.$obj->getUsername().')' :
                $obj))); },
            'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
            'placeholder' => 'label.choose_option',
            'choice_translation_domain' => false,
            'attr' => ['widget' => 'search'],
            'disabled' => count($options['dati'][1]) == 1,
            'required' => true))
          ->add('tipi', ChoiceType::class, array('label' => 'label.tipo',
            'data' => $options['dati'][2],
            'choices' => $options['dati'][3],
            'placeholder' => 'label.choose_option',
            'expanded' => true,
            'multiple' => true,
            'required' => true));
    }
    // aggiunge pulsanti al form
    if ($options['returnUrl']) {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
    } elseif ($options['step'] === null) {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit'));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('formMode');
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('actionUrl');
    $resolver->setDefined('step');
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'formMode' => 'importa',
      'returnUrl' => null,
      'actionUrl' => null,
      'step' => null,
      'dati' => null,
      'data_class' => null));
  }

}
