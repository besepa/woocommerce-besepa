<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 29/9/16
 * Time: 10:49
 */

namespace Besepa\WCPlugin\Exception;


use Besepa\Entity\EntityInterface;

class ResourceAlreadyExistsException extends \Exception
{

    /**
     * @var EntityInterface
     */
    public $entityInstance;

    function __construct(EntityInterface $entity)
    {

        $this->entityInstance = $entity;

        parent::__construct("ResourceAlreadyExists");
    }

}