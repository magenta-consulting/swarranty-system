<?php

namespace Magenta\Bundle\SWarrantyAdminBundle\Admin\Customer;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Magenta\Bundle\SWarrantyAdminBundle\Admin\BaseAdmin;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\CaseAppointment;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\ServiceSheet;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Media\Media;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class ServiceSheetAdmin extends BaseAdmin
{
    protected function configureDatagridFilters(DatagridMapper $filter)
    {
        parent::configureDatagridFilters($filter);
    }

    protected function configureRoutes(RouteCollection $collection)
    {
        parent::configureRoutes($collection);
    }

    public function toString(
        $object
    ) {
        return $object instanceof ServiceSheet
            ? $object->getId().' '.$object->getCreatedAt()->format('d-m-Y')
            : parent::toString($object); // shown in the breadcrumb on the create view
    }

    public function getNewInstance()
    {
        /** @var ServiceSheet $object */
        $object = parent::getNewInstance();
        if (0 === $object->getImages()->count()) {
            $object->addImage(new Media());
        }

        return $object;
    }

    public function isGranted(
        $name, $object = null
    ) {
        return parent::isGranted($name, $object);
    }

    public function createQuery(
        $context = 'list'
    ) {
        $query = parent::createQuery($context);
        $parentFD = $this->getParentFieldDescription();

        return $query;
//        $query->andWhere()
    }

    public function getPersistentParameters()
    {
        $parameters = parent::getPersistentParameters();
        if (!$this->hasRequest()) {
            return $parameters;
        }

        if (empty($org = $this->getCurrentOrganisation(false))) {
            return $parameters;
        }

        return array_merge($parameters, [
            'organisation' => $org->getId(),
        ]);
    }

    protected function configureFormFields(FormMapper $formMapper)
    {
        $c = $this->getConfigurationPool()->getContainer();
        /** @var ProxyQuery $productQuery */
        $apmtQuery = $this->getModelManager()->createQuery(CaseAppointment::class);
        /** @var Expr $expr */
        $expr = $apmtQuery->expr();
        /** @var QueryBuilder $qb */
        $qb = $apmtQuery->getQueryBuilder();
        $caseId = $this->getRequest()->query->getInt('case', 0);
        if (empty($caseId)) {
            /** @var ServiceSheet $ss */
            if (!empty($ss = $this->subject)) {
                if (!empty($case = $ss->getCase())) {
                    $caseId = $case->getId();
                }
            }
        }
        $apmtQuery->andWhere($expr->eq('o.case', $caseId));

        $formMapper
            ->add('images', CollectionType::class,
                [
                    // each entry in the array will be an "media" field
                    'entry_type' => MediaType::class,
                    'allow_add' => true,
                    'allow_delete' => true,
//					'source'        => $c->getParameter('MEDIA_API_BASE_URL') . $c->getParameter('MEDIA_API_PREFIX'),
                    // these options are passed to each "media" type
                    'entry_options' => [
                        'new_on_update' => false,
                        'attr' => ['class' => 'receipt-image'],
                        'context' => 'service_sheet',
                        'provider' => 'sonata.media.provider.image',
                    ],

                    'label' => 'form.label_image',
//					'class'         => Media::class
                ]);

        if ($caseId > 0) {
            //			$formMapper->add('appointment', ModelType::class, [
//				'required'    => false,
//				'query'       => $apmtQuery,
//				'placeholder' => 'Select Appointment',
//				'property'    => 'searchText',
//				'btn_add'     => false
//
//			]);
        }
    }

    protected function verifyDirectParent(
        $parent
    ) {
    }

    /**
     * @param ServiceSheet $object
     */
    public function preValidate(
        $object
    ) {
    }
}
