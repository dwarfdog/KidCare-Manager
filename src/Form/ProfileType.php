<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $emailConstraints = [new Email(['message' => 'Veuillez entrer un email valide'])];
        if ($options['email_required']) {
            $emailConstraints[] = new NotBlank(['message' => 'L\'email est obligatoire quand la validation email est activee']);
        }

        $builder
            ->add('username', TextType::class, [
                'label' => 'Identifiant',
                'constraints' => [new NotBlank(['message' => 'Veuillez choisir un identifiant'])],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => $options['email_required'],
                'constraints' => $emailConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'email_required' => false,
        ]);

        $resolver->setAllowedTypes('email_required', 'bool');
    }
}
