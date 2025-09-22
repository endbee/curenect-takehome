<?php

declare(strict_types=1);

namespace App\FormType;

use App\Entity\Todo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TodoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('task', TextType::class, [
            'label' => 'Task',
            'attr'  => ['placeholder' => 'Enter your task'],
        ])
        ->add('dueAt', DateTimeType::class, [
            'label'   => 'Due',
            'required'=> false,
            'widget'  => 'single_text',
            'input'   => 'datetime_immutable',
            'model_timezone' => 'UTC',
            'view_timezone'  => 'Europe/Berlin',
        ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Todo::class,
            'csrf_protection' => true,
        ]);
    }
}

