<?php

namespace Magenta\Bundle\SWarrantyModelBundle\Entity\Customer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Messaging\MessageTemplate;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Organisation\Organisation;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\Thing;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\ThingChildInterface;
use Magenta\Bundle\SWarrantyModelBundle\Entity\User\User;

/**
 * @ORM\Entity()
 * @ORM\Table(name="customer__registration")
 */
class Registration implements ThingChildInterface
{
    public function getThing(): ?Thing
    {
        return $this->customer;
    }

    /**
     * @var int|null
     * @ORM\Id
     * @ORM\Column(type="integer",options={"unsigned":true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct()
    {
        $this->warranties = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->code = User::generateCharacterCode(null, 6).'-'.$this->createdAt->format('dm-Y');
    }

    public function getOrganisation(): Organisation
    {
        return $this->customer->getOrganisation();
    }

    public function prepareEmailVerificationMessage()
    {
        $org = $this->getOrganisation();
        $mt = $org->getMessageTemplateByType(MessageTemplate::TYPE_REGISTRATION_VERIFICATION);
        if (empty($mt)) {
            return ['recipient' => $this->customer->getEmail(), 'subject' => '', 'body' => ''];
        }
        $bc = $mt->getContent();
        $system = $org->getSystem();
        $protocol = $system->isSslEnabled() ? 'https://' : 'http://';
        if (empty($domain = $org->getAdminDomain())) {
            $domain = $protocol.$org->getSubDomain().'.'.$system->getDomain();
        } else {
            $domain = $protocol.$domain;
        }

        $emailVerUrl = $domain.'/front/verify-email?token='.$this->customer->initiateEmailVerificationToken();
        $emailVerUrl .= '&amp;reg='.$this->id;
        $bc = str_replace('{verification_url}', sprintf('<a href="%1$s">%1$s</a>', $emailVerUrl), $bc);

        return ['recipient' => $this->customer->getEmail(), 'subject' => $mt->getSubject(), 'body' => $bc];
    }

    public function prepareRegCopyMessage()
    {
        $org = $this->getOrganisation();
        $mt = $org->getMessageTemplateByType(MessageTemplate::TYPE_REGISTRATION_COPY);
        if (empty($mt)) {
            return ['recipient' => $this->customer->getEmail(), 'subject' => '', 'body' => ''];
        }
        $bc = $mt->getContent();
        $system = $org->getSystem();
        $bc = str_replace('{name}', $this->customer->getName(), $bc);

        return ['recipient' => $this->customer->getEmail(), 'subject' => $mt->getSubject(), 'body' => $bc];
    }

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Warranty", mappedBy="registration", cascade={"persist","merge"}, orphanRemoval=true)
     */
    protected $warranties;

    public function addWarranty(Warranty $warranty)
    {
        $this->warranties->add($warranty);
        $warranty->setRegistration($this);
        $warranty->setCustomer($this->customer);
    }

    public function removeWarranty(Warranty $warranty)
    {
        $this->warranties->removeElement($warranty);
        $warranty->setRegistration(null);
    }

    /**
     * @var Customer|null
     * @ORM\ManyToOne(targetEntity="Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Customer", cascade={"persist", "merge"}, inversedBy="registrations")
     * @ORM\JoinColumn(name="id_customer", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $customer;

    //	Customer Info

    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $name;

    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $addressUnitNumber;

    /**
     * @var string|null
     * @ORM\Column(name="token", length=250, nullable=true)
     */
    protected $token;

    /**
     * @var \DateTime|null
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $birthDate;

    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $email;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default":true})
     */
    protected $emailSent = false;

    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $homeAddress;

    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $homePostalCode;

    /**
     * @var int|null
     * @ORM\Column(type="integer",nullable=true)
     */
    protected $dialingCode = 65;

    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $telephone;
    //   End of Customer Info

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default":false})
     */
    protected $verified = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $submitted = false;

    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    protected $code;

    //	DIRTY FIELDS
    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    protected $ageGroup;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $hearFromOnlineSearch;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $hearFromOnlineAd;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $hearFromFriendFamily;

    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $hearFromInteriorDesigner;

    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $hearFromShopWalkIn;

    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonInteriorDesigner;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $hearWalkShop;
    /**
     * @var string|null
     * @ORM\Column(type="string",nullable=true)
     */
    protected $hearOthers;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean",nullable=true,  options={"default":false})
     */
    protected $reasonPromotions;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonTheBrand;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonTechnology;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonJapanese;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonTheDesign;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonAffordable;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonDesignerSuggested;
    /**
     * @var bool|null
     * @ORM\Column(type="boolean", nullable=true, options={"default":false})
     */
    protected $reasonFriendFamilySuggested;
    /**
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    protected $reasonOthers;

    // END DIRTY FIELDS

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getWarranties(): Collection
    {
        return $this->warranties;
    }

    public function setWarranties(Collection $warranties): void
    {
        $this->warranties = $warranties;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function setSubmitted(bool $submitted): void
    {
        $this->submitted = $submitted;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getBirthDate(): ?\DateTime
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTime $birthDate): void
    {
        $this->birthDate = $birthDate;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getHomeAddress(): ?string
    {
        return $this->homeAddress;
    }

    public function setHomeAddress(?string $homeAddress): void
    {
        $this->homeAddress = $homeAddress;
    }

    public function getHomePostalCode(): ?string
    {
        return $this->homePostalCode;
    }

    public function setHomePostalCode(?string $homePostalCode): void
    {
        $this->homePostalCode = $homePostalCode;
    }

    public function getAddressUnitNumber(): ?string
    {
        return $this->addressUnitNumber;
    }

    public function setAddressUnitNumber(?string $addressUnitNumber): void
    {
        $this->addressUnitNumber = $addressUnitNumber;
    }

    public function getDialingCode(): ?int
    {
        return $this->dialingCode;
    }

    public function setDialingCode(?int $dialingCode): void
    {
        $this->dialingCode = $dialingCode;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): void
    {
        $this->telephone = $telephone;
    }

    public function getAgeGroup(): ?string
    {
        return $this->ageGroup;
    }

    public function setAgeGroup(?string $ageGroup): void
    {
        $this->ageGroup = $ageGroup;
    }

    public function isHearFromOnlineSearch(): ?bool
    {
        return $this->hearFromOnlineSearch;
    }

    public function setHearFromOnlineSearch(?bool $hearFromOnlineSearch): void
    {
        $this->hearFromOnlineSearch = $hearFromOnlineSearch;
    }

    public function isHearFromOnlineAd(): ?bool
    {
        return $this->hearFromOnlineAd;
    }

    public function setHearFromOnlineAd(?bool $hearFromOnlineAd): void
    {
        $this->hearFromOnlineAd = $hearFromOnlineAd;
    }

    public function isHearFromFriendFamily(): ?bool
    {
        return $this->hearFromFriendFamily;
    }

    public function setHearFromFriendFamily(?bool $hearFromFriendFamily): void
    {
        $this->hearFromFriendFamily = $hearFromFriendFamily;
    }

    public function isReasonInteriorDesigner(): ?bool
    {
        return $this->reasonInteriorDesigner;
    }

    public function setReasonInteriorDesigner(?bool $reasonInteriorDesigner): void
    {
        $this->reasonInteriorDesigner = $reasonInteriorDesigner;
    }

    public function getHearWalkShop(): ?bool
    {
        return $this->hearWalkShop;
    }

    public function setHearWalkShop(?bool $hearWalkShop): void
    {
        $this->hearWalkShop = $hearWalkShop;
    }

    public function getHearOthers(): ?string
    {
        return $this->hearOthers;
    }

    public function setHearOthers(?string $hearOthers): void
    {
        $this->hearOthers = $hearOthers;
    }

    public function isReasonPromotions(): ?bool
    {
        return $this->reasonPromotions;
    }

    public function setReasonPromotions(?bool $reasonPromotions): void
    {
        $this->reasonPromotions = $reasonPromotions;
    }

    public function isReasonTheBrand(): ?bool
    {
        return $this->reasonTheBrand;
    }

    public function setReasonTheBrand(?bool $reasonTheBrand): void
    {
        $this->reasonTheBrand = $reasonTheBrand;
    }

    public function isReasonTechnology(): ?bool
    {
        return $this->reasonTechnology;
    }

    public function setReasonTechnology(?bool $reasonTechnology): void
    {
        $this->reasonTechnology = $reasonTechnology;
    }

    public function isReasonJapanese(): ?bool
    {
        return $this->reasonJapanese;
    }

    public function setReasonJapanese(?bool $reasonJapanese): void
    {
        $this->reasonJapanese = $reasonJapanese;
    }

    public function isReasonTheDesign(): ?bool
    {
        return $this->reasonTheDesign;
    }

    public function setReasonTheDesign(?bool $reasonTheDesign): void
    {
        $this->reasonTheDesign = $reasonTheDesign;
    }

    public function isReasonAffordable(): ?bool
    {
        return $this->reasonAffordable;
    }

    public function setReasonAffordable(?bool $reasonAffordable): void
    {
        $this->reasonAffordable = $reasonAffordable;
    }

    public function isReasonDesignerSuggested(): ?bool
    {
        return $this->reasonDesignerSuggested;
    }

    public function setReasonDesignerSuggested(?bool $reasonDesignerSuggested): void
    {
        $this->reasonDesignerSuggested = $reasonDesignerSuggested;
    }

    public function isReasonFriendFamilySuggested(): ?bool
    {
        return $this->reasonFriendFamilySuggested;
    }

    public function setReasonFriendFamilySuggested(?bool $reasonFriendFamilySuggested): void
    {
        $this->reasonFriendFamilySuggested = $reasonFriendFamilySuggested;
    }

    public function getReasonOthers(): ?string
    {
        return $this->reasonOthers;
    }

    public function setReasonOthers(?string $reasonOthers): void
    {
        $this->reasonOthers = $reasonOthers;
    }

    public function isHearFromInteriorDesigner(): ?bool
    {
        return $this->hearFromInteriorDesigner;
    }

    public function setHearFromInteriorDesigner(?bool $hearFromInteriorDesigner): void
    {
        $this->hearFromInteriorDesigner = $hearFromInteriorDesigner;
    }

    public function isHearFromShopWalkIn(): ?bool
    {
        return $this->hearFromShopWalkIn;
    }

    public function setHearFromShopWalkIn(?bool $hearFromShopWalkIn): void
    {
        $this->hearFromShopWalkIn = $hearFromShopWalkIn;
    }

    public function isEmailSent(): bool
    {
        return $this->emailSent;
    }

    public function setEmailSent(bool $emailSent): void
    {
        $this->emailSent = $emailSent;
    }

    /**
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string|null $tokenfit
     */
    public function setToken(?string $token): void
    {
        $this->token = $token;
    }
}
