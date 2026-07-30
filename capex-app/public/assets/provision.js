// Capex browser provisioning. Runs in the Bitrix24 install iframe, in the admin's
// authenticated session — the only context allowed to create SPA user fields.
// Creates the three SPAs, their fields and custom stages, binds the webhook +
// dashboard placement, then calls BX24.installFinish(). Server-side discovery
// (bin/provision.php --discover) reads the codes back afterwards.

(function () {
    'use strict';

    var S = window.CAPEX.schema;
    var HANDLER = window.CAPEX.handlerBase; // .../public

    var logEl = document.getElementById('capex-log');
    function log(msg, cls) {
        var li = document.createElement('li');
        li.textContent = msg;
        if (cls) { li.className = cls; }
        logEl.appendChild(li);
    }

    // Promisify BX24.callMethod. Rejects on API error with a readable message.
    function call(method, params) {
        return new Promise(function (resolve, reject) {
            BX24.callMethod(method, params || {}, function (res) {
                if (res.error()) {
                    var e = res.error();
                    reject(new Error(method + ': ' + ((e && e.ex && e.ex.error_description) || e)));
                } else {
                    resolve(res.data());
                }
            });
        });
    }

    // Bitrix UF user-type ids. 'text' is a multiline string; 'money' is a CRM type.
    function userType(t) { return t === 'text' ? 'string' : t; }
    // 'double' defaults to PRECISION 0 (rounds to integer!) — set it so FX rates keep decimals.
    function fieldSettings(t) {
        if (t === 'text') { return { ROWS: 4 }; }
        if (t === 'double') { return { PRECISION: 6 }; }
        return {};
    }

    // Find a dynamic type by title, else create it. Returns {entityTypeId, typeId}.
    function ensureType(title) {
        return call('crm.type.list', { start: 0 }).then(function (data) {
            var types = (data && data.types) || [];
            for (var i = 0; i < types.length; i++) {
                var t = types[i];
                if (String(t.title || '').trim() === title) {
                    log('type "' + title + '" exists (#' + t.entityTypeId + ')');
                    return { entityTypeId: String(t.entityTypeId), typeId: String(t.id) };
                }
            }
            return call('crm.type.add', {
                fields: {
                    title: title,
                    isStagesEnabled: 'Y',
                    isCategoriesEnabled: 'N',
                    isBeginCloseDatesEnabled: 'N'
                }
            }).then(function (r) {
                var type = (r && r.type) || {};
                log('created type "' + title + '" (#' + type.entityTypeId + ')', 'ok');
                return { entityTypeId: String(type.entityTypeId), typeId: String(type.id) };
            });
        });
    }

    // Add every schema field (skipping ones already present, matched by title).
    function ensureFields(entityTypeId, typeId, fields) {
        return call('crm.item.fields', { entityTypeId: Number(entityTypeId) }).then(function (data) {
            var live = (data && data.fields) || {};
            var haveTitles = {};
            Object.keys(live).forEach(function (code) {
                if (live[code] && live[code].title) { haveTitles[live[code].title] = true; }
            });

            var keys = Object.keys(fields);
            var i = 0;
            function next() {
                if (i >= keys.length) { return Promise.resolve(); }
                var key = keys[i++];
                var f = fields[key];
                if (haveTitles[f.title]) { return next(); }
                return call('userfieldconfig.add', {
                    moduleId: 'crm',
                    field: {
                        entityId: 'CRM_' + typeId,
                        fieldName: 'UF_CRM_' + typeId + '_' + key.toUpperCase(),
                        userTypeId: userType(f.type),
                        editFormLabel: { en: f.title },
                        listColumnLabel: { en: f.title },
                        settings: fieldSettings(f.type)
                    }
                }).then(function () {
                    log('  field: ' + f.title, 'ok');
                    return next();
                }).catch(function (e) {
                    log('  field skipped: ' + f.title + ' — ' + e.message, 'warn');
                    return next();
                });
            }
            return next();
        });
    }

    // Add the custom stages (create=true) for the request pipeline.
    function ensureStages(entityTypeId, stages) {
        return call('crm.category.list', { entityTypeId: Number(entityTypeId) }).then(function (data) {
            var cats = (data && data.categories) || [];
            var categoryId = cats.length ? String(cats[0].id) : '0';
            var entityId = 'DYNAMIC_' + entityTypeId + '_STAGE_' + categoryId;

            var toCreate = Object.keys(stages)
                .map(function (k) { return stages[k]; })
                .filter(function (s) { return s.create; });

            var i = 0;
            function next() {
                if (i >= toCreate.length) { return Promise.resolve(); }
                var s = toCreate[i++];
                return call('crm.status.add', {
                    fields: {
                        ENTITY_ID: entityId,
                        STATUS_ID: s.status,
                        NAME: s.name,
                        SORT: s.sort || 100
                    }
                }).then(function () {
                    log('  stage: ' + s.name, 'ok');
                    return next();
                }).catch(function (e) {
                    log('  stage skipped: ' + s.name + ' — ' + e.message, 'warn');
                    return next();
                });
            }
            return next();
        });
    }

    // Bind the dashboard placement + the update webhook.
    function bindPlacementsAndEvents(requestEntityTypeId) {
        var dashUrl = HANDLER + '/index.php';
        var hookUrl = HANDLER + '/index.php/webhook';
        return call('placement.bind', {
            PLACEMENT: 'CRM_DYNAMIC_' + requestEntityTypeId + '_LIST_MENU',
            HANDLER: dashUrl,
            TITLE: 'Capex Dashboard'
        }).catch(function (e) { log('placement skipped: ' + e.message, 'warn'); })
        .then(function () {
            return call('event.bind', { event: 'onCrmDynamicItemUpdate', handler: hookUrl })
                .then(function () { log('webhook bound', 'ok'); })
                .catch(function (e) { log('event.bind skipped: ' + e.message, 'warn'); });
        });
    }

    function run() {
        var requestEntityTypeId = null;
        var chain = Promise.resolve();

        Object.keys(S).forEach(function (entityKey) {
            var spec = S[entityKey];
            chain = chain.then(function () {
                log('· ' + spec.title);
                return ensureType(spec.title).then(function (ids) {
                    if (entityKey === 'request') { requestEntityTypeId = ids.entityTypeId; }
                    return ensureFields(ids.entityTypeId, ids.typeId, spec.fields).then(function () {
                        if (spec.stages && Object.keys(spec.stages).length) {
                            return ensureStages(ids.entityTypeId, spec.stages);
                        }
                    });
                });
            });
        });

        chain.then(function () {
            return bindPlacementsAndEvents(requestEntityTypeId);
        }).then(function () {
            document.getElementById('capex-done').hidden = false;
            log('All done — finishing install', 'ok');
            try { BX24.installFinish(); } catch (e) { /* not in install context */ }
        }).catch(function (e) {
            log('FAILED: ' + e.message, 'err');
        });
    }

    if (typeof BX24 !== 'undefined') {
        BX24.init(function () { run(); });
    } else {
        log('BX24 SDK not available — open this from the Bitrix24 app install.', 'err');
    }
})();
