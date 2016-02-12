Azine Email Bundle Upgrade Instructions
==================

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