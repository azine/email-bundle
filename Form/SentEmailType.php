<?php

namespace  Azine\EmailBundle\Form;

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
        $builder->add('recipients', 'text', ['label' => false, 'required' => false]);
        $builder->add('template', 'text', ['label' => false, 'required' => false]);
        $builder->add('sent', 'text', ['label' => false, 'required' => false]);
        $builder->add('variables', 'text', ['label' => false, 'required' => false]);
        $builder->add('token', 'text', ['label' => false, 'required' => false]);

        $builder->add('save', 'submit', ['label' => 'email.dashboard.filter.button.label', 'attr' => ['class' => 'button']]);

    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'sentEmail';
    }
}
