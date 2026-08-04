<?php

namespace App\Form;

use App\Entity\CourseCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CourseCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank()],
            ])
            ->add('parentCategory', ChoiceType::class, [
                'label' => 'Categorie Bank parente',
                'help' => 'Determine dans quelle categorie de budget/analyse Bank Manager cette sous-categorie sera comptabilisee.',
                'choices' => $options['bank_category_choices'],
                'constraints' => [new NotBlank(['message' => 'Choisissez une categorie Bank parente.'])],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icone',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CourseCategory::class, 'bank_category_choices' => []]);
    }
}
