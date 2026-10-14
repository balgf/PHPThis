// Deployment-owned raw-field gate. PHP never receives an ambiguous credential.
function accepted(r) {
    const fields = r.rawHeadersIn;
    if (fields.length > 64) return 'no';
    let credentials = 0;
    for (let index = 0; index < fields.length; index++) {
        const rawName = fields[index][0];
        if (!/^[A-Za-z0-9-]+$/.test(rawName)) return 'no';
        const name = rawName.toLowerCase();
        if (name === 'authorization' || name === 'proxy-authorization'
            || name === 'cookie' || name === 'x-api-key') return 'no';
        if (name === 'example-machine-key') credentials++;
    }
    return credentials <= 1 ? 'yes' : 'no';
}

export default {accepted};
