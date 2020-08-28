<?php

namespace Magenta\Bundle\SWarrantyModelBundle\Service\Media;

use Sonata\MediaBundle\Entity\MediaManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaService extends MediaManager
{
    /** @var ContainerInterface */
    private $container;

    public function generatePrivateUrl($mid, $format = 'admin', $token = 'DEFAULT_ADMIN_TOKEN')
    {
        $mediaPrefix = $this->container->getParameter('MEDIA_API_PREFIX');
        if ('/' === $mediaPrefix) {
            $mediaPrefix = '';
        }

        return $url = $this->container->getParameter('MEDIA_API_BASE_URL').$mediaPrefix.sprintf('/media/%d/binaries/%s/view.json?token=%s', $mid, $format, $token);
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }
}
