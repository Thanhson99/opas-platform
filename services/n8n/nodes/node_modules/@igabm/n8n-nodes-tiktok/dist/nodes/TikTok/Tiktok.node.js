"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.Tiktok = void 0;
const n8n_workflow_1 = require("n8n-workflow");
const TikTokV2_node_1 = require("./V2/TikTokV2.node");
class Tiktok extends n8n_workflow_1.VersionedNodeType {
    constructor() {
        const baseDescription = {
            displayName: 'TikTok',
            name: 'tiktok',
            icon: { light: 'file:tiktok.svg', dark: 'file:tiktok.dark.svg' },
            group: ['output'],
            subtitle: '={{$parameter["operation"] + ": " + $parameter["resource"]}}',
            description: 'Consume the TikTok API',
            defaultVersion: 2,
        };
        const nodeVersions = {
            2: new TikTokV2_node_1.TikTokV2(baseDescription),
        };
        super(nodeVersions, baseDescription);
    }
}
exports.Tiktok = Tiktok;
//# sourceMappingURL=Tiktok.node.js.map