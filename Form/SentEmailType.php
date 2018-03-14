<?php

namespace  Azine\EmailBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class SentEmailType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setMethod('GET');
        $builder->add('recipients', 'text', array('label' => false, 'required' => false));
        $builder->add('template', 'text', array('label' => false, 'required' => false));
        $builder->add('sent', 'text', array('label' => false, 'required' => false));
        $builder->add('variables', 'text', array('label' => false, 'required' => false));
        $builder->add('token', 'text', array('label' => false, 'required' => false));

        $builder->add('save', 'submit', array('label' => 'email.dashboard.filter.button.label', 'attr' => array('class' => 'button')));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'sentEmail';
    }
}
