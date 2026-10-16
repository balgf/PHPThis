<?php

declare(strict_types=1);

// This reviewed reference has no approved AWS packet or deployment record yet.
// A REFERENCE_ONLY receipt must never turn this application-owned gate green.
fwrite(STDERR, "{\"error\":\"real_aws_and_deployment_evidence_required\"}\n");
exit(1);
