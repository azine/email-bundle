Azine Email Bundle Upgrade Instructions
==================

## From 2.1 to dev-master
While cleaning up some code in the bundle (removing potential errors) a few BC breaks were introduced.  

Reason for the BC-Break: As it is a bad idea, to inject the `EntityManager` into a service, as the `EntityManager` could get closed before the usage. It is better to inject the `ManagerRegistry` and get the `EntityManager` from there. 

### Required changes
If you have subclassed any of the following classes from this bundle, you will have to update your services.yml and your implementation as well.
 - `Azine\EmailBundle\Services\AzineNotifierService`
 - `Azine\EmailBundle\Services\AzineTwigSwiftMailer`
 - `Azine\EmailBundle\Services\AzineRecipientProvider`
 - `Azine\EmailBundle\Services\AzineWebViewService`
 
#### update services.yml
Before:
```
arguments:
          entityManager: "@doctrine.orm.entity_manager"
```
After:
```
arguments:
          managerRegistry: "@doctrine"
```

#### update class fields and constructor functions
Before:
```
    /**
     * @var EntityManager
     */
    protected $em;
...    
    public function __construct(..., EntityManager $entityManager, ...) {
        $this->em = $entityManager;
```

After:
```
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;
...
    public function __construct(..., ManagerRegistry $managerRegistry, ...) {
        $this->managerRegistry = $managerRegistry;
```

#### update access to the EntityManager
Before:
```
        $this->em->persist($notification);
```

After:
```
        $this->managerRegistry->getManager()->persist($notification);
```



## From 1.x to 2.0
To support the full tracking functionality of google analytics the tracking parameter names have been changed.

### Required changes

- tracking parameter names in your `services.yml`
```
        -  campaign_param_name: "%azine_email_campaign_param_name%"
        -  campaign_keyword_param_name: "%azine_email_campaign_keyword_param_name%"
        +  tracking_params_campaign_name: "%azine_email_tracking_params_campaign_name%"
        +  tracking_params_campaign_term: "%azine_email_tracking_params_campaign_term%"
        +  tracking_params_campaign_content: "%azine_email_tracking_params_campaign_content%"
        +  tracking_params_campaign_medium: "%azine_email_tracking_params_campaign_medium%"
        +  tracking_params_campaign_source: "%azine_email_tracking_params_campaign_source%"
```

- update your implementation of `TemplateProviderInterface::getCampaignParamsFor($templateId, array $params = null)` to use the new parameter names.

- if you configured special tracking paramter names in your `app/config/config.yml`, then update these as well. (see above)


### Optional changes

- if you use piwik to do the tracking, then install https://plugins.piwik.org/AdvancedCampaignReporting to get the best out of it.