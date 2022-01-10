<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Istituto;


/**
 * IstitutoType - form per la classe Istituto
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
      ->add('tipo', TextType::class, array('label' => 'label.tipo_istituto',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('tipoSigla', TextType::class, array('label' => 'label.sigla_tipo_istituto',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('nome', TextType::class, array('label' => 'label.nome_istituto',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('nomeBreve', TextType::class, array('label' => 'label.nome_breve_istituto',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('email', EmailType::class, array('label' => 'label.email_istituto',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('pec', EmailType::class, array('label' => 'label.pec_istituto',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('urlSito', UrlType::class, array('label' => 'label.url_sito',
        'default_protocol' => 'https',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('urlRegistro', UrlType::class, array('label' => 'label.url_registro',
        'default_protocol' => 'https',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('firmaPreside', TextType::class, array('label' => 'label.firma_preside',
        'required' => true))
      ->add('emailAmministratore', EmailType::class, array('label' => 'label.email_amministratore',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('emailNotifiche', EmailType::class, array('label' => 'label.email_notifiche',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'data_class' => Istituto::class));
  }

}
