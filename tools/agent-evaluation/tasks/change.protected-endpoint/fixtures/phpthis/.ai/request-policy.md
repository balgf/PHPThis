# Request policy

The protected supplied Policy is a synthetic collaborator, not a real authentication implementation. Call authenticate, resolveTenant, authorize in that order before protected SQL or operation input validation. Its only sample credential is the public fixture string. Unknown credentials return401; tenant/action denials403. SQL separately enforces current membership. Do not conflate account membership of principal7 with document ownership. All application responses are private, no-store JSON. Never disclose private_note or exception messages.
