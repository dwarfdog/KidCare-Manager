<?php
namespace App\Form;

use App\Entity\Nanny;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class NannyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un prénom',
                    ]),
                ],
            ])
            ->add('lastname', TextType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un nom',
                    ]),
                ],
            ])
            ->add('hourly_rate', MoneyType::class, [
                'currency' => 'EUR',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un taux horaire',
                    ]),
                    new Positive([
                        'message' => 'Le taux horaire doit être positif',
                    ]),
                ],
            ])
            ->add('meal_rate', MoneyType::class, [
                'currency' => 'EUR',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un prix de repas',
                    ]),
                    new Positive([
                        'message' => 'Le prix du repas doit être positif',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Nanny::class,
        ]);
    }
}