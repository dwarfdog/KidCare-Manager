<?php

namespace App\Form;

use App\Entity\CourseProduct;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CourseShoppingListItemType extends AbstractType
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
            ->add('product', ChoiceType::class, [
                'label' => 'Produit',
                'constraints' => [new NotBlank(['message' => 'Choisissez un produit.'])],
                'choices' => $options['product_choices'],
            ])
            ->add('desiredQuantity', NumberType::class, [
                'label' => 'Quantite souhaitee',
                'scale' => 3,
                'html5' => true,
            ])
            ->add('unit', ChoiceType::class, [
                'label' => 'Unite',
                'choices' => self::UNIT_LABELS,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'product_choices' => []]);
    }
}
