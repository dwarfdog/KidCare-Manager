<?php

namespace App\Form;

use App\Entity\CareTemplate;
use App\Entity\Nanny;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CareTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du template',
                'attr' => [
                    'placeholder' => 'Ex: Semaine type école',
                ]
            ])
            ->add('nanny', EntityType::class, [
                'class' => Nanny::class,
                'label' => 'Nounou',
                'choices' => $options['user']->getNannies(),
                'placeholder' => 'Choisir une nounou',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CareTemplate::class,
            'user' => null,
        ]);

        $resolver->setRequired('user');
        $resolver->setAllowedTypes('user', 'App\Entity\User');
    }
}