<?php

namespace App\Form;

use App\Entity\NannyRate;
use App\Service\ApplicationSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class NannyRateType extends AbstractType
{
    public function __construct(private readonly ApplicationSettings $settings) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hourlyRate', MoneyType::class, [
                'label' => 'Taux horaire',
                'currency' => $this->settings->currency(),
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un taux horaire']),
                    new PositiveOrZero(['message' => 'Le taux horaire doit etre positif ou nul']),
                ],
            ])
            ->add('mealRate', MoneyType::class, [
                'label' => 'Prix repas',
                'currency' => $this->settings->currency(),
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un prix de repas']),
                    new PositiveOrZero(['message' => 'Le prix repas doit etre positif ou nul']),
                ],
            ])
            ->add('effectiveFrom', DateType::class, [
                'label' => 'Date d\'effet',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(['message' => 'Veuillez choisir une date d\'effet'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NannyRate::class,
        ]);
    }
}
