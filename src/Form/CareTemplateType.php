<?php

namespace App\Form;

use App\Entity\CareTemplate;
use App\Entity\Nanny;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CareTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du template',
                'attr' => ['placeholder' => 'Ex: semaine type ecole'],
                'constraints' => [new NotBlank(['message' => 'Veuillez nommer le template'])],
            ])
            ->add('nanny', EntityType::class, [
                'class' => Nanny::class,
                'label' => 'Nounou',
                'choices' => $options['nannies'],
                'choice_label' => 'fullName',
                'placeholder' => 'Choisir une nounou',
                'constraints' => [new NotBlank(['message' => 'Veuillez choisir une nounou'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CareTemplate::class,
            'nannies' => [],
        ]);

        $resolver->setAllowedTypes('nannies', 'array');
    }
}
