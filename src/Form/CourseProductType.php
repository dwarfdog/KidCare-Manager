<?php

namespace App\Form;

use App\Entity\CourseProduct;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CourseProductType extends AbstractType
{
    private const UNIT_LABELS = [
        'A la piece' => CourseProduct::UNIT_PIECE,
        'Au kilo (kg)' => CourseProduct::UNIT_KG,
        'Au gramme (g)' => CourseProduct::UNIT_G,
        'Au litre (l)' => CourseProduct::UNIT_L,
        'Au centilitre/millilitre (ml)' => CourseProduct::UNIT_ML,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du produit',
                'constraints' => [new NotBlank(['message' => 'Le nom du produit est obligatoire.'])],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'placeholder' => 'Aucune',
                'required' => false,
                'choices' => $options['category_choices'],
            ])
            ->add('defaultUnit', ChoiceType::class, [
                'label' => 'Unite de reference',
                'choices' => self::UNIT_LABELS,
                'help' => 'Sert de base au prix au kilo/litre/piece pour les comparatifs.',
            ])
            ->add('packageQuantity', NumberType::class, [
                'label' => 'Quantite habituelle du conditionnement',
                'required' => false,
                'scale' => 3,
                'html5' => true,
                'help' => 'Ex : 0.5 pour un pot de 500g, 1 pour une piece.',
                'constraints' => [new GreaterThan(0)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'category_choices' => []]);
    }
}
