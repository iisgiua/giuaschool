<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Avviso;
use App\Entity\Materia;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * AvvisoType - form per la classe Avviso
 *
 * @author Antonello Dessì
 */
class AvvisoType extends AbstractType {

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(
      private EntityManagerInterface $em) {
  }

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    if ($options['form_mode'] == 'generico') {
      // form generico
      $builder
        ->add('data', DateType::class, ['label' => 'label.data_evento',
					'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'format' => 'dd/MM/yyyy',
					'required' => true])
        ->add('titolo', TextType::class, ['label' => 'label.oggetto',
          'trim' => true,
					'required' => true])
        ->add('sedi', ChoiceType::class, ['label' => 'label.sede',
          'choices' => $options['values'][0],
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5  gs-pr-5'],
          'required' => true])
        ->add('speciali', ChoiceType::class, ['label' => 'label.destinatari_speciali',
          'choices' => ['label.dsga' => 'D', 'label.RSPP' => 'S', 'label.rappresentanti_R' => 'R',
          'label.rappresentanti_I' => 'I', 'label.rappresentanti_P' => 'P'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false])
        ->add('ataTutti', CheckboxType::class, ['label' => 'label.ata_tutti',
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false,
          'mapped' => false])
        ->add('ata', ChoiceType::class, ['label' => 'label.destinatari_ATA',
          'choices' => ['label.ata_amministrativi' => 'A', 'label.ata_tecnici' => 'T',
            'label.ata_collaboratori' => 'C'],
          'placeholder' => false,
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => false])
        ->add('coordinatori', ChoiceType::class, ['label' => 'label.coordinatori',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroCoordinatori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('docenti', ChoiceType::class, ['label' => 'label.docenti',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_materia' => 'M', 'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroDocenti', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('genitori', ChoiceType::class, ['label' => 'label.genitori',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroGenitori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('alunni', ChoiceType::class, ['label' => 'label.alunni',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C',
            'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroAlunni', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('rappresentantiGenitori', ChoiceType::class, ['label' => 'label.rappresentantiGenitori',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroRappresentantiGenitori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('rappresentantiAlunni', ChoiceType::class, ['label' => 'label.rappresentantiAlunni',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_classe' => 'C'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroRappresentantiAlunni', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('testo', MessageType::class, ['label' => 'label.testo',
					'attr' => ['rows' => '4'],
          'trim' => true,
					'required' => true])
        ->add('creaAnnotazione', ChoiceType::class, ['label' => 'label.crea_annotazione',
					'data' => $options['values'][1],
					'choices' => ['label.si' => true, 'label.no' => false],
					'expanded' => true,
					'multiple' => false,
					'label_attr' => ['class' => 'radio-inline'],
					'mapped' => false,
					'required' => true])
        ->add('classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][2],
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'required' => false,
          'mapped' => false])
        ->add('materie', ChoiceType::class, ['label' => 'label.scegli_materie',
          'choices' => $options['values'][3],
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'label_attr' => ['class' => 'checkbox-split-vertical gs-pt-0'],
          'required' => false,
          'mapped' => false])
        ->add('lista_classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][4],
          'placeholder' => 'label.classe',
          'choice_translation_domain' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_value' => 'id',
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['style' => 'width:auto;display:inline-block', 'class' => 'gs-placeholder'],
          'required' => false,
          'mapped' => false]);
      // aggiunge data transform
      $builder->get('speciali')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(string $speciali): array => str_split($speciali),
        // FORM -> DB
        fn(array $speciali): string => implode('', $speciali)));
      $builder->get('ata')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(string $ata): array => str_split($ata),
        // FORM -> DB
        fn(array $ata): string => implode('', $ata)));
      $builder->get('filtroCoordinatori')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroRappresentantiGenitori')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
      $builder->get('filtroRappresentantiAlunni')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
    } elseif ($options['form_mode'] == 'orario') {
      // form orario
      $builder
        ->add('data', DateType::class, ['label' => 'label.data_evento',
					'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'format' => 'dd/MM/yyyy',
					'required' => true])
        ->add('ora', TimeType::class, ['label' => ($options['values'][0] == 'E' ? 'label.ora_entrata' : 'label.ora_uscita'),
					'data' => $options['values'][1]['ora'],
          'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'required' => true,
          'mapped' => false])
        ->add('note', MessageType::class, ['label' => 'label.note',
					'data' => $options['values'][1]['note'],
					'attr' => ['rows' => '3'],
					'required' => false,
          'mapped' => false])
        ->add('sedi', ChoiceType::class, ['label' => 'label.sede',
					'choices' => $options['values'][2],
					'choice_translation_domain' => false,
					'expanded' => true,
					'multiple' => true,
					'choice_value' => 'id',
					'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
					'required' => true])
        ->add('filtroDocenti', HiddenType::class, ['label' => false,
					'required' => false])
        ->add('classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][3],
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'gs-checkbox-inline col-sm-2 gs-pt-1'],
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'required' => true,
          'mapped' => false]);
    } elseif ($options['form_mode'] == 'attivita') {
      // form attività
      $builder
        ->add('data', DateType::class, ['label' => 'label.data_evento',
					'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'format' => 'dd/MM/yyyy',
					'required' => true])
        ->add('inizio', TimeType::class, ['label' => 'label.ora_inizio',
          'data' => $options['values'][1]['inizio'],
          'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'required' => true,
          'mapped' => false])
        ->add('fine', TimeType::class, ['label' => 'label.ora_fine',
          'data' => $options['values'][1]['fine'],
					'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'required' => true,
          'mapped' => false])
        ->add('attivita', MessageType::class, ['label' => 'label.attivita',
					'data' => $options['values'][1]['attivita'],
					'attr' => ['rows' => '3'],
					'required' => true,
          'mapped' => false])
        ->add('sedi', ChoiceType::class, ['label' => 'label.sede',
					'choices' => $options['values'][2],
					'choice_translation_domain' => false,
					'expanded' => true,
					'multiple' => true,
					'choice_value' => 'id',
					'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
					'required' => true])
        ->add('filtroDocenti', HiddenType::class, ['label' => false,
					'required' => false])
        ->add('classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][3],
          'choice_translation_domain' => false,
          'label_attr' => ['class' => 'gs-checkbox-inline col-sm-2 gs-pt-1'],
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'required' => true,
          'mapped' => false]);
    } elseif ($options['form_mode'] == 'personale') {
      // form messaggio indivisuale
      $builder
        ->add('testo', MessageType::class, ['label' => 'label.testo',
					'attr' => ['rows' => '4'],
					'required' => true])
        ->add('sedi', ChoiceType::class, ['label' => 'label.sede',
					'choices' => $options['values'][0],
					'choice_translation_domain' => false,
					'expanded' => true,
					'multiple' => true,
					'choice_value' => 'id',
					'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
					'required' => true])
        ->add('filtroGenitori', HiddenType::class, ['label' => false,
					'required' => true])
        ->add('lista_classi', ChoiceType::class, ['label' => 'label.scegli_classi',
          'choices' => $options['values'][1],
          'choice_translation_domain' => false,
          'placeholder' => 'label.classe',
          'expanded' => false,
          'multiple' => false,
          'choice_value' => 'id',
          'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
          'attr' => ['style' => 'width:auto;display:inline-block', 'class' => 'gs-placeholder'],
          'required' => false,
          'mapped' => false]);
    } elseif ($options['form_mode'] == 'coordinatore') {
      // form coordinatore
      $builder
        ->add('titolo', TextType::class, ['label' => 'label.oggetto',
          'trim' => true,
					'required' => true])
        ->add('testo', MessageType::class, ['label' => 'label.testo',
					'attr' => ['rows' => '3'],
					'required' => true])
        ->add('creaAnnotazione', ChoiceType::class, ['label' => 'label.crea_annotazione',
					'data' => $options['values'][0],
					'choices' => ['label.si' => true, 'label.no' => false],
					'expanded' => true,
					'multiple' => false,
					'label_attr' => ['class' => 'radio-inline'],
					'mapped' => false,
					'required' => true])
        ->add('docenti', ChoiceType::class, ['label' => 'label.docenti_classe',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('genitori', ChoiceType::class, ['label' => 'label.genitori_classe',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroGenitori', HiddenType::class, ['label' => false,
          'required' => false])
        ->add('alunni', ChoiceType::class, ['label' => 'label.alunni_classe',
          'choices' => ['label.filtro_nessuno' => 'N', 'label.filtro_tutti' => 'T', 'label.filtro_utenti' => 'U'],
          'placeholder' => false,
          'expanded' => false,
          'multiple' => false,
          'choice_attr' => fn(): array => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:12em;display:inline'],
          'required' => false])
        ->add('filtroAlunni', HiddenType::class, ['label' => false,
          'required' => false]);

    } elseif ($options['form_mode'] == 'verifica' || $options['form_mode'] == 'compito') {
      // form verifica/compito
      $builder
        ->add('data', DateType::class, ['label' => 'label.data',
					'widget' => 'single_text',
					'html5' => false,
					'attr' => ['widget' => 'gs-picker'],
					'format' => 'dd/MM/yyyy',
					'required' => true])
        ->add('cattedra', ChoiceType::class, ['label' => 'label.cattedra',
					'choices' => $options['values'][0],
					'expanded' => false,
					'multiple' => false,
					'placeholder' => 'label.scegli_cattedra',
					'choice_translation_domain' => false,
					'required' => true])
        ->add('materia', HiddenType::class, ['label' => false,
					'required' => false])
        ->add('testo', MessageType::class, ['label' => $options['form_mode'] == 'verifica' ? 'label.descrizione_verifica' : 'label.descrizione_compito',
					'attr' => ['rows' => '3'],
					'required' => true])
        ->add('genitori', ChoiceType::class, ['label' => false,
					'choices' => ['label.filtro_tutti' => 'C', 'label.filtro_utenti' => 'U'],
					'placeholder' => false,
					'expanded' => false,
					'multiple' => false,
					'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
					'attr' => ['class' => 'gs-placeholder', 'style' => 'width:auto;display:inline'],
					'required' => true])
        ->add('filtroGenitori', HiddenType::class, ['label' => false,
					'required' => false]);
      // aggiunge data transform
      $builder->get('materia')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?Materia $materia): string => $materia ? (string) $materia->getId() : '',
        // FORM -> DB
        fn(?string $materia): ?Materia => empty($materia) ? null : $this->em->getReference(Materia::class, (int) $materia)));
    }
    // aggiunge data transform
    if (in_array($options['form_mode'], ['generico', 'orario', 'attivita', 'personale'])) {
      $builder->get('sedi')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?Collection $sedi): array => $sedi ? $sedi->toArray() : [],
        // FORM -> DB
        fn(?array $sedi): ArrayCollection => new ArrayCollection($sedi ?? [])));
    }
    if (in_array($options['form_mode'], ['generico', 'orario', 'attivita'])) {
        $builder->get('filtroDocenti')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
    }
    if (in_array($options['form_mode'], ['generico', 'personale', 'coordinatore', 'verifica', 'compito'])) {
      $builder->get('filtroGenitori')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
    }
    if (in_array($options['form_mode'], ['generico', 'coordinatore'])) {
      $builder->get('filtroAlunni')->addModelTransformer(new CallbackTransformer(
        // DB -> FORM
        fn(?array $filtro): string => $filtro ? implode(',', $filtro) : '',
        // FORM -> DB
        fn(?string $filtro): array => $filtro ? explode(',', (string) $filtro) : []));
    }
    // aggiunge pulsanti
    $builder
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
				'attr' => ['class' => 'btn-primary btn gs-mr-3']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
				'attr' => ['onclick' => "location.href='".$options['return_url']."'"]]);

  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults([
			'form_mode' => 'generico',
			'return_url' => null,
			'values' => [],
			'data_class' => Avviso::class]);
  }

}
