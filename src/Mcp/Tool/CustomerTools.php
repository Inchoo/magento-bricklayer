<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Attribute\McpTool;

/**
 * Customer Tools
 *
 * Provides MCP tools for managing Magento customers.
 */
class CustomerTools
{
    /**
     * Retrieves customer data by email.
     *
     * @param string $email Customer email address
     * @return array<string, mixed> Customer data
     */
    #[McpTool(
        name: 'customer-get',
        description: 'Retrieves customer data by email address. Use fields to limit response.'
    )]
    public function getCustomer(string $email, string $fields = ''): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        if ($email === '') {
            return ['error' => true, 'message' => 'Email is required'];
        }

        try {
            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $customer = $customerRepository->get($email);

            return $this->formatCustomerData($customer, true, $fields);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['error' => true, 'message' => "Customer not found: $email"];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists customers with filtering and pagination.
     *
     * @param int $pageSize Number of results per page
     * @param int $currentPage Current page number
     * @param string $sortField Field to sort by
     * @param string $sortDir Sort direction (ASC or DESC)
     * @return array<string, mixed> List of customers
     */
    #[McpTool(
        name: 'customer-list',
        description: 'Lists customers with pagination and sorting. Use fields to limit columns. Set count_only=true to get total count without data.'
    )]
    public function listCustomers(
        int $pageSize = 20,
        int $currentPage = 1,
        string $sortField = 'entity_id',
        string $sortDir = 'DESC',
        bool $count_only = false,
        string $fields = ''
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            $sortOrder = $sortOrderBuilder->setField($sortField)->setDirection($sortDir)->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $customerRepository->getList($searchCriteria);

            if ($count_only) {
                return [
                    'total' => $result->getTotalCount(),
                    'count_only' => true,
                ];
            }

            $customers = [];
            foreach ($result->getItems() as $customer) {
                $customers[] = $this->formatCustomerData($customer, false, $fields);
            }

            return [
                'total_count' => $result->getTotalCount(),
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'items' => $customers,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Creates a new customer account.
     *
     * @param string $email Customer email
     * @param string $firstname First name
     * @param string $lastname Last name
     * @param int $storeId Store ID
     * @param int $groupId Customer group ID
     * @return array<string, mixed> Created customer data or error
     */
    #[McpTool(
        name: 'customer-create',
        description: 'Creates a new customer account'
    )]
    public function createCustomer(
        string $email,
        string $firstname,
        string $lastname,
        int $storeId = 1,
        int $groupId = 1
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $customerFactory = MagentoBootstrap::get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class);

            $customer = $customerFactory->create();
            $customer->setEmail($email)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setStoreId($storeId)
                ->setGroupId($groupId);

            $savedCustomer = $customerRepository->save($customer);

            return ['success' => true, 'customer' => $this->formatCustomerData($savedCustomer, true)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Updates customer data.
     *
     * @param int $customerId Customer ID
     * @param string $firstname New first name (optional)
     * @param string $lastname New last name (optional)
     * @param int $groupId New customer group ID (0 to skip)
     * @return array<string, mixed> Updated customer data or error
     */
    #[McpTool(
        name: 'customer-update',
        description: 'Updates customer data'
    )]
    public function updateCustomer(
        int $customerId,
        string $firstname = '',
        string $lastname = '',
        int $groupId = 0
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $customer = $customerRepository->getById($customerId);

            if ($firstname !== '') {
                $customer->setFirstname($firstname);
            }
            if ($lastname !== '') {
                $customer->setLastname($lastname);
            }
            if ($groupId > 0) {
                $customer->setGroupId($groupId);
            }

            $savedCustomer = $customerRepository->save($customer);

            return ['success' => true, 'customer' => $this->formatCustomerData($savedCustomer, true)];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['success' => false, 'error' => "Customer not found: $customerId"];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deletes a customer account.
     *
     * @param int $customerId Customer ID
     * @return array<string, mixed> Deletion result
     */
    #[McpTool(
        name: 'customer-delete',
        description: 'Deletes a customer account'
    )]
    public function deleteCustomer(int $customerId): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        $registry = MagentoBootstrap::get(\Magento\Framework\Registry::class);

        try {
            $registry->unregister('isSecureArea');
            $registry->register('isSecureArea', true);

            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $customerRepository->deleteById($customerId);

            return ['success' => true, 'message' => "Customer $customerId deleted"];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $registry->unregister('isSecureArea');
            $registry->register('isSecureArea', false);
        }
    }

    /**
     * Lists all customer groups.
     *
     * @return array<string, mixed> List of customer groups
     */
    #[McpTool(
        name: 'customer-groups-list',
        description: 'Lists all customer groups'
    )]
    public function listCustomerGroups(): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $groupRepository = MagentoBootstrap::get(\Magento\Customer\Api\GroupRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);

            $result = $groupRepository->getList($searchCriteriaBuilder->create());

            $groups = [];
            foreach ($result->getItems() as $group) {
                $groups[] = [
                    'id' => (int) $group->getId(),
                    'code' => $group->getCode(),
                    'tax_class_id' => (int) $group->getTaxClassId(),
                ];
            }

            return ['groups' => $groups];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists orders for a specific customer.
     *
     * @param int $customerId Customer ID
     * @param int $pageSize Page size
     * @param int $currentPage Current page
     * @return array<string, mixed> List of customer orders
     */
    #[McpTool(
        name: 'customer-orders',
        description: 'Lists orders for a specific customer'
    )]
    public function getCustomerOrders(int $customerId, int $pageSize = 20, int $currentPage = 1): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $orderRepository = MagentoBootstrap::get(\Magento\Sales\Api\OrderRepositoryInterface::class);
            $searchCriteriaBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $sortOrderBuilder = MagentoBootstrap::get(\Magento\Framework\Api\SortOrderBuilder::class);

            $sortOrder = $sortOrderBuilder->setField('created_at')->setDirection('DESC')->create();
            $searchCriteria = $searchCriteriaBuilder
                ->addFilter('customer_id', $customerId)
                ->addSortOrder($sortOrder)
                ->setPageSize($pageSize)
                ->setCurrentPage($currentPage)
                ->create();

            $result = $orderRepository->getList($searchCriteria);

            $orders = [];
            foreach ($result->getItems() as $order) {
                $orders[] = [
                    'entity_id' => (int) $order->getEntityId(),
                    'increment_id' => $order->getIncrementId(),
                    'status' => $order->getStatus(),
                    'grand_total' => (float) $order->getGrandTotal(),
                    'created_at' => $order->getCreatedAt(),
                ];
            }

            return [
                'customer_id' => $customerId,
                'total_count' => $result->getTotalCount(),
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'orders' => $orders,
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lists customer addresses.
     *
     * @param int $customerId Customer ID
     * @return array<string, mixed> List of customer addresses
     */
    #[McpTool(
        name: 'customer-addresses',
        description: 'Lists customer addresses'
    )]
    public function getCustomerAddresses(int $customerId): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $customer = $customerRepository->getById($customerId);

            $addresses = [];
            foreach ($customer->getAddresses() ?? [] as $address) {
                $addresses[] = $this->formatAddressData($address);
            }

            return [
                'customer_id' => $customerId,
                'address_count' => count($addresses),
                'addresses' => $addresses,
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['error' => true, 'message' => "Customer not found: $customerId"];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Adds an address to a customer.
     *
     * @param int $customerId Customer ID
     * @param string $firstname First name
     * @param string $lastname Last name
     * @param string $street Street address (use | for multiple lines)
     * @param string $city City
     * @param string $postcode Postal code
     * @param string $countryId Country code (e.g., "US")
     * @param string $telephone Phone number
     * @param string $regionCode Region/state code (optional)
     * @param bool $defaultBilling Set as default billing address
     * @param bool $defaultShipping Set as default shipping address
     * @return array<string, mixed> Created address data or error
     */
    #[McpTool(
        name: 'customer-address-create',
        description: 'Adds an address to a customer'
    )]
    public function createCustomerAddress(
        int $customerId,
        string $firstname,
        string $lastname,
        string $street,
        string $city,
        string $postcode,
        string $countryId,
        string $telephone,
        string $regionCode = '',
        bool $defaultBilling = false,
        bool $defaultShipping = false
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $addressRepository = MagentoBootstrap::get(\Magento\Customer\Api\AddressRepositoryInterface::class);
            $addressFactory = MagentoBootstrap::get(\Magento\Customer\Api\Data\AddressInterfaceFactory::class);
            $regionFactory = MagentoBootstrap::get(\Magento\Customer\Api\Data\RegionInterfaceFactory::class);

            $address = $addressFactory->create();
            $address->setCustomerId($customerId)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setStreet(explode('|', $street))
                ->setCity($city)
                ->setPostcode($postcode)
                ->setCountryId($countryId)
                ->setTelephone($telephone)
                ->setIsDefaultBilling($defaultBilling)
                ->setIsDefaultShipping($defaultShipping);

            if ($regionCode !== '') {
                $region = $regionFactory->create();
                $region->setRegionCode($regionCode);
                $address->setRegion($region);
            }

            $savedAddress = $addressRepository->save($address);

            return [
                'success' => true,
                'address' => $this->formatAddressData($savedAddress),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Updates a customer address.
     *
     * @param int $addressId Address ID
     * @param string $firstname First name (optional)
     * @param string $lastname Last name (optional)
     * @param string $street Street address (use | for multiple lines, optional)
     * @param string $city City (optional)
     * @param string $postcode Postal code (optional)
     * @param string $telephone Phone number (optional)
     * @return array<string, mixed> Updated address data or error
     */
    #[McpTool(
        name: 'customer-address-update',
        description: 'Updates a customer address'
    )]
    public function updateCustomerAddress(
        int $addressId,
        string $firstname = '',
        string $lastname = '',
        string $street = '',
        string $city = '',
        string $postcode = '',
        string $telephone = ''
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $addressRepository = MagentoBootstrap::get(\Magento\Customer\Api\AddressRepositoryInterface::class);
            $address = $addressRepository->getById($addressId);

            if ($firstname !== '') {
                $address->setFirstname($firstname);
            }
            if ($lastname !== '') {
                $address->setLastname($lastname);
            }
            if ($street !== '') {
                $address->setStreet(explode('|', $street));
            }
            if ($city !== '') {
                $address->setCity($city);
            }
            if ($postcode !== '') {
                $address->setPostcode($postcode);
            }
            if ($telephone !== '') {
                $address->setTelephone($telephone);
            }

            $savedAddress = $addressRepository->save($address);

            return [
                'success' => true,
                'address' => $this->formatAddressData($savedAddress),
            ];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['success' => false, 'error' => "Address not found: $addressId"];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deletes a customer address.
     *
     * @param int $addressId Address ID
     * @return array<string, mixed> Deletion result
     */
    #[McpTool(
        name: 'customer-address-delete',
        description: 'Deletes a customer address'
    )]
    public function deleteCustomerAddress(int $addressId): array
    {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        try {
            $addressRepository = MagentoBootstrap::get(\Magento\Customer\Api\AddressRepositoryInterface::class);
            $addressRepository->deleteById($addressId);

            return ['success' => true, 'message' => "Address $addressId deleted"];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return ['success' => false, 'error' => "Address not found: $addressId"];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validates customer data before create/update.
     *
     * @param string $email Customer email
     * @param string $firstname First name
     * @param string $lastname Last name
     * @param int $websiteId Website ID
     * @return array<string, mixed> Validation result
     */
    #[McpTool(
        name: 'customer-validate',
        description: 'Validates customer data before create/update'
    )]
    public function validateCustomer(
        string $email,
        string $firstname = '',
        string $lastname = '',
        int $websiteId = 1
    ): array {
        if (!MagentoBootstrap::isInitialized()) {
            return ['error' => true, 'message' => 'Magento not initialized'];
        }

        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Check if email already exists
        try {
            $customerRepository = MagentoBootstrap::get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
            $customerRepository->get($email, $websiteId);
            $errors[] = "Customer with email $email already exists";
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Email is available
        } catch (\Throwable $e) {
            $errors[] = 'Error checking email: ' . $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'email' => $email,
            'errors' => $errors,
        ];
    }

    /**
     * Format address data for output
     *
     * @param object $address
     * @return array<string, mixed>
     */
    private function formatAddressData(object $address): array
    {
        $region = $address->getRegion();
        return [
            'id' => (int) $address->getId(),
            'customer_id' => (int) $address->getCustomerId(),
            'firstname' => $address->getFirstname(),
            'lastname' => $address->getLastname(),
            'street' => $address->getStreet(),
            'city' => $address->getCity(),
            'postcode' => $address->getPostcode(),
            'country_id' => $address->getCountryId(),
            'telephone' => $address->getTelephone(),
            'region_code' => $region ? $region->getRegionCode() : null,
            'region' => $region ? $region->getRegion() : null,
            'default_billing' => (bool) $address->isDefaultBilling(),
            'default_shipping' => (bool) $address->isDefaultShipping(),
        ];
    }

    /**
     * Format customer data for output
     *
     * @param object $customer
     * @param bool $includeAddresses
     * @return array<string, mixed>
     */
    private function formatCustomerData(object $customer, bool $includeAddresses, string $fields = ''): array
    {
        $data = [
            'id' => (int) $customer->getId(),
            'email' => $customer->getEmail(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'group_id' => (int) $customer->getGroupId(),
            'store_id' => (int) $customer->getStoreId(),
            'website_id' => (int) $customer->getWebsiteId(),
            'created_at' => $customer->getCreatedAt(),
            'updated_at' => $customer->getUpdatedAt(),
        ];

        if ($includeAddresses) {
            $addresses = [];
            foreach ($customer->getAddresses() ?? [] as $address) {
                $addresses[] = [
                    'id' => (int) $address->getId(),
                    'firstname' => $address->getFirstname(),
                    'lastname' => $address->getLastname(),
                    'street' => $address->getStreet(),
                    'city' => $address->getCity(),
                    'postcode' => $address->getPostcode(),
                    'country_id' => $address->getCountryId(),
                    'telephone' => $address->getTelephone(),
                    'default_billing' => $address->isDefaultBilling(),
                    'default_shipping' => $address->isDefaultShipping(),
                ];
            }
            $data['addresses'] = $addresses;
        }

        return $this->filterFields($data, $fields);
    }

    private function filterFields(array $data, string $fields): array
    {
        if ($fields === '') {
            return $data;
        }

        $requested = array_map('trim', explode(',', $fields));
        return array_intersect_key($data, array_flip($requested));
    }
}
