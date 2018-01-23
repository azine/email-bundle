<?php

namespace  Azine\EmailBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SentEmailType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setMethod('GET');
        $builder->add('recipients', TextType::class, ['label' => false, 'required' => false]);
        $builder->add('template', TextType::class, ['label' => false, 'required' => false]);
        $builder->add('sent', TextType::class, ['label' => false, 'required' => false]);
        $builder->add('variables', TextType::class, ['label' => false, 'required' => false]);
        $builder->add('token', TextType::class, ['label' => false, 'required' => false]);

        $builder->add('filter', SubmitType::class, ['label' => 'email.dashboard.filter.button.label', 'attr' => ['class' => 'button']]);

    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'sentEmail';
    }
}
