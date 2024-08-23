<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Istituto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * IstitutoType - form per la classe Istituto
 *
 * @author Antonello Dessì
 */
class IstitutoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('tipo', TextType::class, ['label' => 'label.tipo_istituto',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('tipoSigla', TextType::class, ['label' => 'label.sigla_tipo_istituto',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('nome', TextType::class, ['label' => 'label.nome_istituto',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('nomeBreve', TextType::class, ['label' => 'label.nome_breve_istituto',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('email', EmailType::class, ['label' => 'label.email_istituto',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('pec', EmailType::class, ['label' => 'label.pec_istituto',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('urlSito', UrlType::class, ['label' => 'label.url_sito',
        'default_protocol' => 'https',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('urlRegistro', UrlType::class, ['label' => 'label.url_registro',
        'default_protocol' => 'https',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('firmaPreside', TextType::class, ['label' => 'label.firma_preside',
        'required' => true])
      ->add('emailAmministratore', EmailType::class, ['label' => 'label.email_amministratore',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true])
      ->add('emailNotifiche', EmailType::class, ['label' => 'label.email_notifiche',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('return_url');
    $resolver->setDefaults([
      'return_url' => null,
      'data_class' => Istituto::class]);
  }

}
