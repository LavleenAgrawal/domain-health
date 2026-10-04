'use client';

import { useCallback, useEffect, useState } from 'react';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';
const statusStyle = { queued: 'badge-neutral', checking: 'badge-amber', completed: 'badge-green', failed: 'badge-red' };

function apiErrorMessage(payload, fallback) {
  const validationErrors = Object.values(payload?.errors || {}).flat().filter(Boolean);
  return validationErrors[0] || payload?.message || payload?.detail || fallback;
}

function validateDomainInput(rawValue) {
  let value = rawValue.trim().toLowerCase().replace(/\.+$/, '');
  if (!value) return 'Enter a domain or email address.';
  if (value.includes('://') || /[/?#\\]/.test(value)) return 'Enter only a domain or email address, not a URL.';
  if (value.includes('@')) {
    const parts = value.split('@');
    const local = parts[0] || '';
    if (parts.length !== 2 || !local || local.length > 64 || local.startsWith('.') || local.endsWith('.') || local.includes('..') || !/^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+$/i.test(local)) return 'That email address is not valid.';
    value = parts[1];
  }
  if (!value || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(value) || value.includes(':')) return 'Enter a domain or email address, not an IP address.';
  let ascii;
  try { ascii = new URL(`http://${value}`).hostname; } catch { return 'That domain or email address is not valid.'; }
  if (ascii.length > 253 || !/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i.test(ascii)) return 'That domain or email address is not valid.';
  return '';
}

async function readApiResponse(response) {
  const body = await response.text();
  let payload;
  try {
    payload = body ? JSON.parse(body) : {};
  } catch {
    throw new Error(`The API at ${API} returned HTML instead of JSON (HTTP ${response.status}). Start the Laravel API with “php artisan serve --port=8001” and confirm NEXT_PUBLIC_API_URL points to it.`);
  }
  if (!response.ok) throw new Error(apiErrorMessage(payload, `API request failed (HTTP ${response.status}).`));
  return payload;
}

function Icon({ name, size = 20 }) {
  const paths = {
    activity: <><path d="M3 12h3l2.3-7 4.4 14 2.3-7H21" /></>,
    building: <><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M7 7h2M11 7h2M15 7h2M7 11h2M11 11h2M15 11h2M9 21v-5h6v5" /></>,
    check: <path d="m5 12 4 4L19 6" />,
    chevron: <path d="m9 18 6-6-6-6" />,
    close: <><path d="M18 6 6 18M6 6l12 12" /></>,
    clipboard: <><rect x="8" y="5" width="10" height="15" rx="2" /><path d="M16 5V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v1M8 9H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h8" /></>,
    cloud: <><path d="M17.5 19H9a6 6 0 1 1 5.8-7.5A4.5 4.5 0 1 1 17.5 19Z" /><path d="M12 11v6M9.5 13.5 12 11l2.5 2.5" /></>,
    file: <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" /><path d="M14 2v6h6M8 13h8M8 17h5" /></>,
    globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c2.4 2.5 3.6 5.5 3.6 9S14.4 18.5 12 21c-2.4-2.5-3.6-5.5-3.6-9S9.6 5.5 12 3Z" /></>,
    info: <><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></>,
    lock: <><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></>,
    search: <><circle cx="11" cy="11" r="6" /><path d="m16 16 4 4" /></>,
    spark: <path d="m12 2 1.55 6.45L20 10l-6.45 1.55L12 18l-1.55-6.45L4 10l6.45-1.55L12 2Z" />,
    shield: <><path d="M12 3 20 6v6c0 4.8-3.3 8-8 9-4.7-1-8-4.2-8-9V6l8-3Z" /><path d="m8.5 12 2.2 2.2 4.8-5" /></>,
  };
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name]}</svg>;
}

function CopyButton({ value }) { return <button className="copy-button" type="button" aria-label={`Copy ${value}`} onClick={() => navigator.clipboard.writeText(value)}><Icon name="clipboard" size={14} /> Copy</button>; }

const dnsGuide = {
  A: ['Website address (IPv4)', 'Connects the domain to its IPv4 server address.'],
  AAAA: ['Website address (IPv6)', 'Connects the domain to its IPv6 server address.'],
  MX: ['Mail delivery', 'Shows which servers receive email for this domain.'],
  TXT: ['Domain text records', 'Contains verification and email-security instructions.'],
  CNAME: ['Domain aliases', 'Points one hostname to another hostname.'],
  NS: ['DNS provider', 'Shows the nameservers responsible for this domain.'],
  SPF: ['Sender protection (SPF)', 'Lists the servers allowed to send email for this domain.'],
  DMARC: ['Email policy (DMARC)', 'Tells receiving mail systems how to handle suspicious messages.'],
  DKIM: ['Email signature (DKIM)', 'Publishes the key used to verify signed outgoing email.'],
  PTR: ['Reverse DNS', 'Maps a server address back to a hostname.'],
  CAA: ['Certificate authority', 'Controls which providers may issue web certificates.'],
  SRV: ['Service records', 'Points named services to the correct server and port.'],
};

function readableValue(value) {
  if (typeof value === 'string') return value;
  if (value?.host) return `${value.host}${Number.isFinite(value.priority) ? ` — priority ${value.priority}` : ''}`;
  return JSON.stringify(value);
}

function recordTone(record) {
  if (record?.status === 'NOERROR' && record?.values?.length) return 'is-good';
  if (['NOT_CHECKED', 'NOT_APPLICABLE'].includes(record?.status)) return 'is-neutral';
  return 'is-warning';
}

function recordStatus(record) {
  if (record?.status === 'NOERROR' && record?.values?.length) return 'Found';
  if (record?.status === 'NOERROR') return 'Not found';
  if (record?.status === 'NOT_CHECKED') return 'Not checked';
  if (record?.status === 'NOT_APPLICABLE') return 'Not needed';
  if (record?.status === 'NXDOMAIN') return 'Domain not found';
  if (record?.status === 'TIMEOUT') return 'Timed out';
  return record?.status ? record.status.replaceAll('_', ' ').toLowerCase() : 'Unknown';
}

function reportSummary(row, tool) {
  if (row.status === 'failed') return 'This value could not be checked. Review the message below, correct the input, and try again.';
  if (tool === 'provider') {
    const provider = row.findings?.provider?.provider;
    return provider && provider !== 'Unknown' ? `The mail records point to ${provider}. This is based on public DNS evidence.` : 'The mail records do not clearly identify Google Workspace or Microsoft 365.';
  }
  const status = row.findings?.blacklist?.status;
  if (status === 'LISTED') return 'At least one enabled blocklist reported a listing. Review the source and target before taking action.';
  if (status === 'CLEAN') return 'The configured blocklist checks completed and none reported a listing. DNS and email-security details are shown below.';
  if (status === 'NOT_CONFIGURED') return 'DNS checks completed, but this deployment has no blocklist provider configured.';
  return 'DNS information was checked, but blocklist coverage is incomplete, so this result cannot be called clean or listed with confidence.';
}

function nextSteps(row) {
  const dns = row.dns_records || {};
  const steps = [];
  if (Object.values(dns).some(record => record?.status === 'TIMEOUT')) steps.push('Retry the check. One or more DNS lookups timed out, so the report is incomplete.');
  if (!dns.MX?.values?.length) steps.push('Add or verify MX records if this domain should receive email.');
  if (!dns.SPF?.values?.length) steps.push('Publish one SPF record to identify the services allowed to send email for this domain.');
  if (!dns.DMARC?.values?.length) steps.push('Publish a DMARC record to define how receiving systems should handle failed email authentication.');
  if (dns.DKIM?.status === 'NOT_CHECKED') steps.push('Enter your DKIM selector and run the check again if you want DKIM verification.');
  if (dns.DKIM && !['NOT_CHECKED', 'NOT_APPLICABLE'].includes(dns.DKIM.status) && !dns.DKIM.values?.length) steps.push('Verify the DKIM selector and publish the matching DKIM record in DNS.');
  const blacklist = row.findings?.blacklist;
  if (blacklist?.status === 'NOT_CONFIGURED') steps.push('Configure an authorized blocklist source before relying on a clean or listed result.');
  if (blacklist?.sources?.some(source => source.status === 'ACCESS_ERROR')) steps.push('A blocklist rejected this server\'s DNS access. Configure that provider\'s authorized resolver or API, then retry.');
  if (!steps.length) steps.push('No immediate DNS changes are suggested by these public records. Continue monitoring after DNS or email-provider changes.');
  return steps;
}

const sourceLabels = { surbl: 'SURBL', spamhaus_zen: 'Spamhaus ZEN', spamrats: 'SpamRATS' };

function sourceStatus(status) {
  return {
    LISTED: 'Listed',
    NOT_LISTED: 'Not listed',
    NOT_CONFIGURED: 'Not configured',
    NOT_APPLICABLE: 'Not applicable',
    ACCESS_ERROR: 'Access rejected',
    ERROR: 'Check failed',
  }[status] || status?.replaceAll('_', ' ').toLowerCase() || 'Unknown';
}

function sourceTone(status) {
  if (status === 'NOT_LISTED') return 'is-good';
  if (['NOT_CONFIGURED', 'NOT_APPLICABLE'].includes(status)) return 'is-neutral';
  return 'is-warning';
}

function BlocklistReport({ finding }) {
  const sources = finding?.sources || [];
  if (!sources.length) return null;
  return <section className="report-section"><div className="report-section-heading"><div><p className="section-kicker">Blocklist coverage</p><h3>Every provider and query</h3></div><span>{finding.checked_queries || 0} queries</span></div><div className="dns-card-grid">{sources.map((source, index) => <article className="dns-card" key={`${source.source}-${source.target || index}`}><div className="dns-card-heading"><div><span>LIST</span><strong>{sourceLabels[source.source] || source.source}</strong></div><em className={sourceTone(source.status)}>{sourceStatus(source.status)}</em></div>{source.status === 'NOT_CONFIGURED' ? <p>This optional provider is not enabled and is not counted as failed coverage.</p> : <><p>{source.target ? `Checked ${source.target}` : 'No compatible IP address was available to query.'}</p>{source.query && <ul><li>Query: {source.query}</li>{source.answers?.map(answer => <li key={answer}>Response: {answer}</li>)}</ul>}</>}</article>)}</div></section>;
}

function Evidence({ row, tool }) {
  const [technicalOpen, setTechnicalOpen] = useState(false);
  const records = Object.entries(row.dns_records || {});
  return <div className="evidence-report"><section className="plain-summary"><span className="report-icon"><Icon name="info" size={18} /></span><div><p className="section-kicker">What this means</p><h3>{reportSummary(row, tool)}</h3></div></section>{tool === 'blacklist' && <BlocklistReport finding={row.findings?.blacklist} />}{records.length > 0 && <section className="report-section"><div className="report-section-heading"><div><p className="section-kicker">DNS explained</p><h3>Your records in plain language</h3></div><span>{records.length} checks</span></div><div className="dns-card-grid">{records.map(([type, record]) => { const [label, explanation] = dnsGuide[type] || [type, 'A public DNS record returned for this domain.']; const values = record?.values || []; return <article className="dns-card" key={type}><div className="dns-card-heading"><div><span>{type}</span><strong>{label}</strong></div><em className={recordTone(record)}>{recordStatus(record)}</em></div><p>{explanation}</p>{values.length > 0 && <ul>{values.slice(0, 5).map((value, index) => <li key={`${type}-${index}`}>{readableValue(value)}</li>)}{values.length > 5 && <li>+ {values.length - 5} more records</li>}</ul>}</article>; })}</div></section>}<section className="report-section next-steps"><p className="section-kicker">Recommended next steps</p><h3>What you can do</h3><ol>{nextSteps(row).map((step, index) => <li key={step}><span>{index + 1}</span><p>{step}</p></li>)}</ol></section><section className="technical-section"><button type="button" className="technical-toggle" onClick={() => setTechnicalOpen(!technicalOpen)} aria-expanded={technicalOpen}><span><Icon name="file" size={16} /> Technical JSON</span><span>{technicalOpen ? 'Hide raw data' : 'View raw data'} <Icon name="chevron" size={14} /></span></button>{technicalOpen && <pre className="evidence-code" tabIndex="0">{JSON.stringify({ dns: row.dns_records, findings: row.findings, errors: row.errors, mode: tool === 'provider' ? 'DNS-based detection only' : undefined }, null, 2)}</pre>}</section></div>;
}

function ResultRows({ row, tool }) {
  const [open, setOpen] = useState(false);
  const hasReport = Boolean(row.dns_records || row.errors);
  return <><tr><td data-label="Domain"><strong>{row.normalized_domain || row.original_input}</strong><CopyButton value={row.normalized_domain || row.original_input} /></td><td data-label="Status"><span className={`badge ${statusStyle[row.status] || statusStyle.queued}`}>{row.status}</span></td><td data-label="Finding">{tool === 'provider' ? (row.findings?.provider?.provider || '—') : (row.findings?.blacklist?.status || '—')}</td><td data-label="Report">{hasReport ? <button type="button" onClick={() => setOpen(!open)} className="evidence-button" aria-expanded={open}>{open ? 'Hide report' : 'View report'} <Icon name="chevron" size={14} /></button> : <span className="result-waiting">Waiting…</span>}</td></tr>{open && <tr className="evidence-row"><td colSpan="4"><Evidence row={row} tool={tool} /></td></tr>}</>;
}

function ToolSwitcher({ tool, setTool }) {
  const items = [
    { value: 'blacklist', title: 'Domain health', text: 'DNS, blocklists and mail records', icon: 'globe' },
    { value: 'provider', title: 'Mail provider', text: 'Google Workspace or Microsoft 365', icon: 'building' },
  ];
  return <div className="tool-switcher" role="tablist" aria-label="Choose a check">{items.map(item => <button key={item.value} type="button" role="tab" aria-selected={tool === item.value} onClick={() => setTool(item.value)} className={`tool-option ${tool === item.value ? 'is-active' : ''}`}><span className="tool-option-icon"><Icon name={item.icon} size={19} /></span><span><strong>{item.title}</strong><small>{item.text}</small></span>{tool === item.value && <span className="tool-option-check"><Icon name="check" size={15} /></span>}</button>)}</div>;
}

function DkimHelpModal({ onClose }) {
  return <div className="modal-layer" role="presentation"><button type="button" className="modal-backdrop" onClick={onClose} aria-label="Close DKIM help" /><section className="help-modal" role="dialog" aria-modal="true" aria-labelledby="dkim-help-title"><button type="button" className="icon-button modal-close" onClick={onClose} aria-label="Close DKIM help"><Icon name="close" size={19} /></button><span className="help-icon"><Icon name="info" size={22} /></span><p className="section-kicker">Quick guide</p><h2 id="dkim-help-title">What is a DKIM selector?</h2><p className="help-intro">It is the short name that points to your domain&apos;s public email-signing key. Common examples are <strong>selector1</strong>, <strong>google</strong>, or <strong>default</strong>.</p><ol className="help-steps"><li><span>1</span><div><strong>Open your email admin</strong><p>Sign in to Google Workspace, Microsoft 365, or your email hosting panel.</p></div></li><li><span>2</span><div><strong>Find DKIM or email authentication</strong><p>Look under email security, domain settings, or DNS setup.</p></div></li><li><span>3</span><div><strong>Copy the selector</strong><p>It is the part before <code>._domainkey</code>. For <code>selector1._domainkey.example.com</code>, enter <code>selector1</code>.</p></div></li></ol><div className="help-note"><Icon name="info" size={16} /><p>Not sure? Leave it blank. The health check will still run; only the DKIM lookup will be skipped.</p></div><button type="button" className="button-primary help-done" onClick={onClose}>Got it</button></section></div>;
}

function ResultsDrawer({ job, rows, tool, search, setSearch, filter, setFilter, onClose, onRetry }) {
  const progress = job?.progress || {};
  const complete = progress.total > 0 && progress.processed >= progress.total;
  return <div className="drawer-layer"><button type="button" className="drawer-backdrop" onClick={onClose} aria-label="Close results" /><aside className="results-drawer" role="dialog" aria-modal="true" aria-labelledby="results-drawer-title"><header className="drawer-header"><div><span className="drawer-kicker"><span className="live-dot" /> Live results</span><h2 id="results-drawer-title">{tool === 'blacklist' ? 'Domain health report' : 'Mail provider report'}</h2><p>Results update automatically as each check finishes.</p></div><button type="button" className="icon-button" onClick={onClose} aria-label="Close results"><Icon name="close" size={20} /></button></header><div className="drawer-scroll"><section className="results-section"><div className="progress-card"><div className="progress-main"><div className="progress-icon"><Icon name="activity" size={20} /></div><div><span className="section-kicker">Live check</span><h2>{progress.processed || 0} of {progress.total || 0} domains processed</h2><div className="progress-track"><span style={{ width: `${progress.total ? (progress.processed / progress.total) * 100 : 0}%` }} /></div><p>{progress.completed || 0} complete <i /> {progress.failed || 0} need attention</p></div></div><div className="progress-actions">{complete ? <a className="button-secondary" href={`${API}/checks/${job.id}/export`}>Export full report</a> : <span className="button-secondary is-disabled">Export when complete</span>}{progress.failed > 0 && <button type="button" className="button-quiet" onClick={onRetry}>Retry failed</button>}</div></div><div className="results-toolbar"><div><h2>Results</h2><p>Clear evidence for every domain.</p></div><div className="result-filters"><label><Icon name="search" size={17} /><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Find a domain" aria-label="Find a domain" /></label><select value={filter} onChange={event => setFilter(event.target.value)} aria-label="Filter results"><option value="">All results</option><optgroup label="Processing status"><option value="status:queued">Queued</option><option value="status:checking">Checking</option><option value="status:completed">Completed</option><option value="status:failed">Failed</option></optgroup>{tool === 'blacklist' ? <optgroup label="Blacklist result"><option value="finding:CLEAN">Clean</option><option value="finding:LISTED">Listed</option></optgroup> : <optgroup label="Mail provider"><option value="finding:Google Workspace">Google Workspace</option><option value="finding:Microsoft 365">Microsoft 365</option><option value="finding:Other">Other</option><option value="finding:Not Detected">Not Detected</option></optgroup>}</select></div></div><div className="results-table-wrap"><table className="results-table"><thead><tr><th>Domain</th><th>Status</th><th>Finding</th><th><span className="sr-only">Report</span></th></tr></thead><tbody>{rows.map(row => <ResultRows key={row.id} row={row} tool={tool} />)}</tbody></table>{rows.length === 0 && <div className="empty-results"><span><Icon name="activity" size={22} /></span><strong>{filter || search ? 'No matching results' : 'Checking your domain'}</strong><p>{filter || search ? 'Try another filter or search term.' : 'The first result will appear here automatically.'}</p></div>}</div></section></div></aside></div>;
}

function UploadSummary({ preview }) {
  if (!preview) return null;
  return <div className="upload-summary"><Icon name="check" size={17} /><div><strong>File ready to check</strong><span>{preview.total_values} {preview.total_values === 1 ? 'value' : 'values'} found</span></div></div>;
}

export default function ToolPage() {
  const [tool, setTool] = useState('blacklist'); const [mode, setMode] = useState('single'); const [input, setInput] = useState(''); const [selector, setSelector] = useState(''); const [allRecords, setAllRecords] = useState(false); const [file, setFile] = useState(null); const [preview, setPreview] = useState(null); const [isDragging, setIsDragging] = useState(false);
  const [job, setJob] = useState(null); const [rows, setRows] = useState([]); const [search, setSearch] = useState(''); const [filter, setFilter] = useState(''); const [notice, setNotice] = useState(''); const [submitting, setSubmitting] = useState(false); const [resultsOpen, setResultsOpen] = useState(false); const [dkimHelpOpen, setDkimHelpOpen] = useState(false); const [inputTouched, setInputTouched] = useState(false);
  const chooseTool = value => { setTool(value); setJob(null); setRows([]); setSearch(''); setFilter(''); setNotice(''); setResultsOpen(false); setInputTouched(false); };
  const loadRows = useCallback(async jobId => { const params = new URLSearchParams({ per_page: '100', ...(search && { search }) }); if (filter) { const separator = filter.indexOf(':'); params.set(filter.slice(0, separator), filter.slice(separator + 1)); } try { const body = await readApiResponse(await fetch(`${API}/checks/${jobId}/results?${params}`, { credentials: 'include', headers: { Accept: 'application/json' } })); setRows(body.data.data); } catch (error) { setNotice(error.message); } }, [search, filter]);
  const refresh = useCallback(async jobId => { try { setJob(await readApiResponse(await fetch(`${API}/checks/${jobId}`, { credentials: 'include', headers: { Accept: 'application/json' } }))); await loadRows(jobId); } catch (error) { setNotice(error.message); } }, [loadRows]);
  useEffect(() => { if (job?.id) loadRows(job.id); }, [search, filter, job?.id, loadRows]);
  useEffect(() => {
    const modalOpen = resultsOpen || dkimHelpOpen;
    document.body.classList.toggle('modal-open', modalOpen);
    const closeOnEscape = event => {
      if (event.key !== 'Escape') return;
      if (dkimHelpOpen) setDkimHelpOpen(false);
      else setResultsOpen(false);
    };
    if (modalOpen) window.addEventListener('keydown', closeOnEscape);
    return () => {
      document.body.classList.remove('modal-open');
      window.removeEventListener('keydown', closeOnEscape);
    };
  }, [resultsOpen, dkimHelpOpen]);
  useEffect(() => {
    if (!job?.id) return;
    const complete = job.progress?.total > 0 && job.progress.processed >= job.progress.total;
    if (complete) return;
    const timer = window.setInterval(() => refresh(job.id), 2000);
    return () => window.clearInterval(timer);
  }, [job?.id, job?.progress?.processed, job?.progress?.total, refresh]);
  async function previewFile(nextFile) { setPreview(null); setNotice(''); if (!nextFile) { setFile(null); return; } setFile(nextFile); const body = new FormData(); body.append('file', nextFile); try { setPreview(await readApiResponse(await fetch(`${API}/checks/upload-preview`, { method: 'POST', body, credentials: 'include', headers: { Accept: 'application/json' } }))); } catch (error) { setFile(null); setNotice(error.message); } }
  function dropFile(event) { event.preventDefault(); setIsDragging(false); previewFile(event.dataTransfer.files?.[0]); }
  async function submit(event) { event.preventDefault(); if (mode === 'single') { setInputTouched(true); if (validateDomainInput(input)) return; } if (mode === 'bulk' && !file) return; setSubmitting(true); setNotice(''); const body = new FormData(); body.append('tool', tool); body.append('show_all_records', allRecords ? '1' : '0'); if (mode === 'single') { body.append('input', input); body.append('selector', selector); } else { body.append('file', file); } try { const data = await readApiResponse(await fetch(`${API}/checks`, { method: 'POST', body, credentials: 'include', headers: { Accept: 'application/json' } })); setJob(data); setRows([]); setResultsOpen(true); setNotice('Check started. Live results are open.'); } catch (error) { setNotice(error.message); } finally { setSubmitting(false); } }
  const inputError = mode === 'single' ? validateDomainInput(input) : '';
  const title = tool === 'blacklist' ? 'Check a domain' : 'Identify a mail provider'; const description = tool === 'blacklist' ? 'Run a DNS health check with transparent blocklist coverage.' : 'Identify Google Workspace and Microsoft 365 from MX records.'; const buttonText = tool === 'blacklist' ? 'Run health check' : 'Detect provider';
  return <main className="app-shell"><header className="topbar"><a className="brand" href="#top" aria-label="Domain Health home"><span className="brand-mark"><Icon name="shield" size={18} /></span><span>Domain<span>Health</span></span></a><div className="privacy-note"><Icon name="lock" size={15} /> DNS-only analysis</div></header>
    <section className="hero" id="top"><p>DNS health and email infrastructure, made simple.</p></section>
    <section className="workspace" aria-label="Domain health tools"><ToolSwitcher tool={tool} setTool={chooseTool} /><div className="check-card"><div className="check-card-heading"><div className="heading-icon"><Icon name={tool === 'blacklist' ? 'globe' : 'building'} size={22} /></div><div><h2>{title}</h2><p>{description}</p></div></div><form onSubmit={submit}><div className="mode-toggle" role="radiogroup" aria-label="Input mode"><label className={mode === 'single' ? 'selected' : ''}><input type="radio" checked={mode === 'single'} onChange={() => setMode('single')} /><Icon name="search" size={16} /> One domain</label><label className={mode === 'bulk' ? 'selected' : ''}><input type="radio" checked={mode === 'bulk'} onChange={() => setMode('bulk')} /><Icon name="cloud" size={16} /> Upload a list</label></div>
      {mode === 'single' ? <div className={`single-input ${tool === 'blacklist' ? 'with-selector' : ''}`}><label className="field-label"><span>Domain or email address</span><div className={`input-wrap ${inputTouched && inputError ? 'has-error' : ''}`}><Icon name="globe" size={19} /><input value={input} onChange={event => setInput(event.target.value)} onBlur={() => setInputTouched(true)} aria-invalid={Boolean(inputTouched && inputError)} aria-describedby="domain-input-error" placeholder="example.com or admin@example.com" autoComplete="off" /></div>{inputTouched && inputError && <p id="domain-input-error" className="field-error"><Icon name="info" size={14} /> {inputError}</p>}</label>{tool === 'blacklist' && <div className="field-label selector-field"><label htmlFor="dkim-selector">DKIM selector <em>Optional</em></label><div className="input-wrap"><input id="dkim-selector" value={selector} onChange={event => setSelector(event.target.value)} placeholder="e.g. selector1" autoComplete="off" /></div><button type="button" className="dkim-help-trigger" onClick={() => setDkimHelpOpen(true)}><Icon name="info" size={14} /> What is this, and where do I find it?</button></div>}</div> : <div className="upload-area"><input id="domain-file" type="file" accept=".csv,.txt,text/plain" onChange={event => { previewFile(event.target.files?.[0]); event.target.value = ''; }} /><label htmlFor="domain-file" className={`upload-dropzone ${isDragging ? 'is-dragging' : ''}`} onDragEnter={event => { event.preventDefault(); setIsDragging(true); }} onDragOver={event => { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; setIsDragging(true); }} onDragLeave={event => { event.preventDefault(); setIsDragging(false); }} onDrop={dropFile}><span className="upload-icon"><Icon name={file ? 'file' : 'cloud'} size={24} /></span><span><strong>{isDragging ? 'Drop the file to upload it' : (file ? file.name : 'Drop your file here, or browse')}</strong><small>{file ? `${Math.ceil(file.size / 1024)} KB · all values detected automatically` : 'CSV or TXT · every domain and email will be included'}</small></span><span className="browse-label">Browse</span></label><UploadSummary preview={preview} /></div>}
      <div className="form-footer">{tool === 'blacklist' ? <label className="advanced-toggle"><input type="checkbox" checked={allRecords} onChange={event => setAllRecords(event.target.checked)} /><span></span>Include all supported DNS records <em>Advanced</em></label> : <p className="helper-text"><Icon name="lock" size={14} /> DNS evidence only — no claims about subscriptions or ownership.</p>}<button disabled={submitting || (mode === 'bulk' && (!file || !preview))} className="button-primary run-button">{submitting ? 'Running...' : <><span>{buttonText}</span><Icon name="chevron" size={18} /></>}</button></div></form>{notice && <p className="notice" role="status">{notice}</p>}</div></section>
    {job && !resultsOpen && <button type="button" className="view-results-button" onClick={() => setResultsOpen(true)}><span><span className="live-dot" /> {job.progress?.processed || 0} of {job.progress?.total || 0} checked</span><strong>View live results <Icon name="chevron" size={17} /></strong></button>}
    {resultsOpen && job && <ResultsDrawer job={job} rows={rows} tool={tool} search={search} setSearch={setSearch} filter={filter} setFilter={setFilter} onClose={() => setResultsOpen(false)} onRetry={async () => { await fetch(`${API}/checks/${job.id}/retry`, { method: 'POST', credentials: 'include' }); refresh(job.id); }} />}
    {dkimHelpOpen && <DkimHelpModal onClose={() => setDkimHelpOpen(false)} />}</main>;
}
