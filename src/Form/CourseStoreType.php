<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CourseStoreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du magasin',
                'constraints' => [new NotBlank(['message' => 'Le nom du magasin est obligatoire.'])],
            ])
            ->add('chain', TextType::class, [
                'label' => 'Enseigne',
                'required' => false,
                'help' => 'Utilisee pour regrouper les points de vente d\'une meme enseigne dans les comparatifs (ex : Carrefour).',
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
