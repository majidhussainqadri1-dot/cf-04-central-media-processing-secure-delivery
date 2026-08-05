#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="1.2.0-rc.1"
DIST="$ROOT/dist"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
rm -rf "$DIST"; mkdir -p "$DIST" "$TMP/sabri-central-media"
rsync -a --delete "$ROOT/sabri-central-media/" "$TMP/sabri-central-media/"
find "$TMP/sabri-central-media" -type f -exec touch -t 202001010000.00 {} +
python3 - "$ROOT" "$TMP" "$VERSION" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1]); temp=pathlib.Path(sys.argv[2]); version=sys.argv[3]
plugin=temp/'sabri-central-media'
files=[]
for path in sorted(p for p in plugin.rglob('*') if p.is_file()):
    raw=path.read_bytes(); files.append({'path':path.relative_to(plugin).as_posix(),'size':len(raw),'sha256':hashlib.sha256(raw).hexdigest()})
manifest={'module':'CF-04','version':version,'schema_version':'1.4.0','contract_version':'1.4.0','runtime_default':'disabled','requirements_complete_in_source':33,'cross_plan_directives':['CHAT-XFER-001','CHAT-QA-001'],'source_date_epoch':1577836800,'files':files}
(root/'dist/MANIFEST.json').write_text(json.dumps(manifest,sort_keys=True,separators=(',',':'))+'\n')
sbom={'bomFormat':'CycloneDX','specVersion':'1.5','serialNumber':'urn:uuid:'+hashlib.sha256((version+'CF-04').encode()).hexdigest()[:32],'version':1,'metadata':{'component':{'type':'application','name':'cf-04-sabri-central-media','version':version}},'components':[{'type':'file','name':x['path'],'hashes':[{'alg':'SHA-256','content':x['sha256']}]} for x in files]}
(root/'dist/SBOM.json').write_text(json.dumps(sbom,sort_keys=True,separators=(',',':'))+'\n')
PY
(
  cd "$TMP"
  find sabri-central-media -type f -print | LC_ALL=C sort | zip -X -q "$DIST/cf-04-sabri-central-media-$VERSION.zip" -@
)
(
  cd "$DIST"
  sha256sum "cf-04-sabri-central-media-$VERSION.zip" MANIFEST.json SBOM.json > CHECKSUMS.sha256
)
printf '%s\n' "$DIST/cf-04-sabri-central-media-$VERSION.zip"
