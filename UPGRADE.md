Azine Email Bundle Upgrade Instructions
==================

## From 3.0 to 0dev-master
While cleaning up some code in the bundle to conform with the latest coding guidelines and best practices, the following BC-Breaks occured.
 
- If you have implemented your own version of AzineEmailTemplateController (extending it), ou must add the parameter to your implementation as well in the following functions.
  - \Azine\PlatformBundle\Controller\AzineEmailTemplateController::webPreViewAction
  - \Azine\PlatformBundle\Controller\AzineEmailTemplateController::webViewAction
  - \Azine\PlatformBundle\Controller\AzineEmailTemplateController::serveImageAction

Before:
```
    public function webPreViewAction($template, $format = null){ ...
```
After: update the arguments in your implementation of AzineEmailTemplateController
```
    public function webPreViewAction(Request $request, $template, $format = null){ ...
```

Before:
```
    public function webViewAction ($token){ ...
```
After: update the arguments in your implementation of AzineEmailTemplateController
```
    public function webViewAction (Request $request, $token){ ...
```

Before:
```
    public function serveImageAction($folderKey, $filename){ ...
```
After: update the arguments in your implementation of AzineEmailTemplateController
```
    public function serveImageAction(Request $request, $folderKey, $filename){ ...
```

## From 2.1 to 3.0
While cleaning up some code in the bundle (removing potential errors and fixing a memory leak) a few BC breaks were introduced. They should be rather straight forward to fix though.

Reasons for the BC-Breaks: 
- As it is a bad idea, to inject the `EntityManager` into a service, as the `EntityManager` could get closed before the usage. It is better to inject the `ManagerRegistry` and get the `EntityManager` from there.
- The usage of the `Logger` in the AzineTwigSwiftMailer and the AzineNotifierService caused a memory leak. 

### Required changes
If you have sub-classed any of the following classes from this bundle, you will have to update your services.yml and your implementation as well.
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
After: rename the EntityManager(Registry) and remove the logger from the arguments list
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

After: rename the EntityManager(Registry) and remove the logger from the constructor 
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