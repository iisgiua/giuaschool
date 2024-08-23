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
    if ($options['form_mode'] == 'nuovo') {
      // form nuovo anno
      $builder
        ->setAction($options['action_url']);
      if ($options['values'][0] <= 7) {
        $builder
          ->add('submit', SubmitType::class, ['label' => $options['values'][0] == 0 ? 'label.start' : 'label.next']);
      } else {
        $builder
          ->add('submit', SubmitType::class, ['label' => 'label.submit',
            'attr' => ['style' => 'visibility:hidden']]);
      }
    } elseif ($options['form_mode'] == 'archivia') {
      // form archivia
      $builder
        ->add('tipo', ChoiceType::class, ['label' => 'label.tipo_archivio',
          'choices' => ['label.registro_docente' => 'D', 'label.registro_sostegno' => 'S',
            'label.registro_classe' => 'C', 'label.documenti_scrutinio' => 'U',
            'label.archivio_circolari' => 'R'],
          'required' => true])
        ->add('selezione', ChoiceType::class, ['label' => 'label.selezione_archivio',
          'choices' => ['label.tutti' => 'T', 'label.selezionato' => 'S', 'label.da_selezionato' => 'D'],
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'required' => true])
        ->add('docente', ChoiceType::class, ['label' => 'label.docente_curricolare',
          'choices' => $options['values'][0],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'row_attr' => ['id' => 'row_modulo_docente'],
          'required' => false])
        ->add('sostegno', ChoiceType::class, ['label' => 'label.docente_sostegno',
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'row_attr' => ['id' => 'row_modulo_sostegno'],
          'required' => false])
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'choices' => $options['values'][2],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'row_attr' => ['id' => 'row_modulo_classe'],
          'required' => false])
        ->add('circolare', ChoiceType::class, ['label' => 'label.archivio_circolari',
          'choices' => $options['values'][3],
          'choice_label' => fn($obj) => $obj->getNumero().' - '.$obj->getOggetto(),
          'choice_value' => fn($obj) => $obj ? $obj->getId() : $obj,
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'row_attr' => ['id' => 'row_modulo_circolare'],
          'required' => false]);
    } elseif ($options['form_mode'] == 'staff') {
      // form staff
      $builder
        ->add('docente', ChoiceType::class, ['label' => 'label.docente',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'disabled' => ($options['values'][0] != null),
          'required' => true])
        ->add('sede', ChoiceType::class, ['label' => 'label.sede',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'choice_value' => 'id',
          'placeholder' => 'label.qualsiasi_sede',
          'choice_translation_domain' => false,
          'required' => false]);
    } elseif ($options['form_mode'] == 'coordinatori') {
      // form coordinatori/segretari
      $builder
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'disabled' => ($options['values'][0] != null),
          'required' => true])
        ->add('docente', ChoiceType::class, ['label' => 'label.docente',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'choice_value' => 'id',
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => true]);
    } elseif ($options['form_mode'] == 'log') {
      // form log
      $builder
        ->add('data', DateType::class, ['label' => 'label.data',
          'data' => $options['values'][0],
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true])
        ->add('ora', TimeType::class, ['label' => 'label.ora_inizio',
          'data' => $options['values'][1],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true]);
    } elseif ($options['form_mode'] == 'email') {
      // form configurazione email
      $builder
        ->add('server', ChoiceType::class, ['label' => 'label.mailserver_tipo',
          'data' => $options['values'][0],
          'choices' => ['label.mailserver_smtp' => 'smtp', 'label.mailserver_sendmail' => 'sendmail',
            'label.mailserver_gmail' => 'gmail+smtp', 'label.mailserver_php' => 'php'],
          'help' => 'message.mailserver_help_smtp',
          'required' => true])
        ->add('user', TextType::class, ['label' => 'label.mailserver_user',
          'data' => $options['values'][1],
          'attr' => ['widget' => 'gs-row-start'],
          'required' => false])
        ->add('password', PasswordType::class, ['label' => 'label.mailserver_password',
          'data' => $options['values'][2],
          'always_empty' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => false])
        ->add('host', TextType::class, ['label' => 'label.mailserver_host',
          'data' => $options['values'][3],
          'attr' => ['widget' => 'gs-row-start'],
          'required' => false])
        ->add('port', IntegerType::class, ['label' => 'label.mailserver_port',
          'data' => $options['values'][4],
          'attr' => ['widget' => 'gs-row-end'],
          'required' => false])
        ->add('email', EmailType::class, ['label' => 'label.mailserver_email',
          'required' => true]);
    } elseif ($options['form_mode'] == 'rappresentanti') {
      // form rappresentanti
      $builder
        ->add('utente', ChoiceType::class, ['label' => 'label.utente',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_label' => fn($obj) => $obj instanceOf Alunno ? $obj.' - '.$obj->getClasse() :
            ($obj instanceOf Genitore ? $obj.' - '.$obj->getAlunno().' - '.$obj->getAlunno()->getClasse() :
            ($obj instanceOf Docente ? $obj.' ('.$obj->getUsername().')' :
            $obj)),
          'choice_value' => fn($obj) => is_object($obj) ? $obj->getId() : $obj,
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'disabled' => count($options['values'][1]) == 1,
          'required' => true])
        ->add('tipi', ChoiceType::class, ['label' => 'label.tipo',
          'data' => $options['values'][2],
          'choices' => $options['values'][3],
          'placeholder' => 'label.choose_option',
          'expanded' => true,
          'multiple' => true,
          'required' => true]);
    } elseif ($options['form_mode'] == 'rspp') {
      // form rspp
      $builder
      ->add('docente', ChoiceType::class, ['label' => 'label.docente',
        'data' => $options['values'][0],
        'choices' => $options['values'][1],
        'choice_value' => 'id',
        'placeholder' => 'label.nessuno',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => false]);
    } elseif ($options['form_mode'] == 'telegram') {
      // form configurazione telegram
      $builder
        ->add('bot', TextType::class, ['label' => 'label.telegram_bot',
          'data' => $options['values'][0],
          'required' => false])
        ->add('token', TextType::class, ['label' => 'label.telegram_token',
          'data' => $options['values'][1],
          'required' => false]);
    } elseif ($options['form_mode'] == 'classe') {
      // form cerca classe
      $builder
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'choice_value' => 'id',
          'placeholder' => 'label.scegli_classe',
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false]);
    }
    // aggiunge pulsanti al form
    if ($options['return_url']) {
      $builder
        ->add('submit', SubmitType::class, ['label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']])
        ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
    } elseif ($options['form_mode'] != 'nuovo') {
      $builder
        ->add('submit', SubmitType::class, ['label' => 'label.submit']);
    }

  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('return_url');
    $resolver->setDefined('action_url');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'form_mode' => 'importa',
      'return_url' => null,
      'action_url' => null,
      'values' => [],
      'data_class' => null]);
  }

}
