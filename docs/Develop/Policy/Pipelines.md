# Pipelines

Pipelines allows you to gather data across multiple audit classes in a single policy.
While the policy structure and format remains the same, pipelines can contain a complex 
amount of configuration in the `parameters` section.

Pipelines are achieved by using the `Drutiny\Audit\AuditAnalysisPipeline` class.

## Pipeline example
```yaml
title: Domain verficiation errors
class: Drutiny\Audit\AuditAnalysisPipeline
description: When your domain is unverified, our system will error.
build_parameters:
    verification_code: target.verification_code
parameters:
    pipeline:
        - name: logs
          class: Drutiny\SumoLogic\Audit\QueryAnalysis
          parameters:
            ^query: _sourceCategory=syslog "[ERROR] domain {target.domain} is unverified."
          continueIf: logs.records|length > 0
        - name: dns
          class: Drutiny\Audit\DNS\DnsAnalysis
          parameters:
            type: TXT
            $zone: target.domain
            variables:
                verified: dns !== false and verification_code in dns.txt
    failIf: logs.records|length > 0
success: No errors found.
failure: |
    Found {{ logs.records|length }} errors in the log file.

    {% if not dns.verified %}
    Your DNS is not verified. It requires the following DNS record:

    ```
    {{ target.domain }} 3600 IN TXT {{ verification_code }}
    ```
    
    {% endif %}
```

Lets walk through the example above:

1. The policy defines `build_parameters` with a single parameters called 
   `verification_code`. This is just to make it easier to reference later.
2. There is a parameter called `pipeline` which contains an array of sequential
   audits to gather data from.
3. Each stage in the pipeline contains three mandatory keys: name, class and 
   parameters.
   * The `name` key specifies the name of the pipeline and is also a prefix for 
     referencing variables/tokens built in the audit.
   * The `class` key specifies which class to use in the stage.
   * The `parameters` key defines the parameters to pass into the class.
   * There is an optional key called `continueIf` which allows you to stop the
     pipeline at a given point if the evaluated expression returns `false`.
4. By using the caret (`^`) infront of a parameter name, the value will have
   have token replacement processing conducted over it. This will translate the 
   value `{target.domain}` into the actual domain of the target.
   See [Dynamic parameters](DynamicParameters.md).
5. By using the dollars sign (`$`) infront of a parameter name, the value will
   by evaluated by twig. This allows the `$zone` parameter value to be the actual 
   domain of the target. See [Dynamic parameters](DynamicParameters.md).
6. The `failIf` parameter refers to data gathered by the `logs` stage of the 
   pipeline. The `records` variable from that part of the pipeline is 
   accessible via `logs.records`.
7. The `failure` message uses the pipeline names `logs` and `dns` to access
   tokens gathered by those respective audits in the pipeline.

Pipelines do not have `build_parameters`. You can use the policy's `build_parameters` to evaluate
parameters before the pipeline starts. You can also use the `variables` parameter within each pipeline
to process outputs inbetween pipeline stages ready for the next stage. Finally parameters can use
process markers on their keys to allow for dynamic processing of parameters. 
See [Dynamic parameters](DynamicParameters.md).
