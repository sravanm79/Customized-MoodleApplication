# Builds ../docs/moodle-load-and-storage-results.xlsx from results/*
import csv, gzip, json, datetime
from collections import defaultdict
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.chart import LineChart, Reference
from openpyxl.comments import Comment

F = 'Arial'
H = Font(name=F, bold=True, color='FFFFFF'); HF = PatternFill('solid', fgColor='1F4E78')
B = Font(name=F); BB = Font(name=F, bold=True); T = Font(name=F, bold=True, size=14)
INP = Font(name=F, color='0000FF'); YEL = PatternFill('solid', fgColor='FFFF00')
thin = Side(style='thin', color='BFBFBF'); BOX = Border(left=thin, right=thin, top=thin, bottom=thin)

def per_minute(p):
    rows = [r for r in csv.DictReader(gzip.open(p + '_raw.csv.gz', 'rt')) if r['metric_name'] in ('http_req_duration', 'vus')]
    t0 = min(int(r['timestamp']) for r in rows)
    lat, vus = defaultdict(list), defaultdict(int)
    for r in rows:
        m = (int(r['timestamp']) - t0) // 60
        if r['metric_name'] == 'vus': vus[m] = max(vus[m], int(float(r['metric_value'])))
        elif r['name'] != 'ajax_poll': lat[m].append(float(r['metric_value']))
    mon = list(csv.DictReader(open(p + '_monitor.csv')))
    day = datetime.datetime.fromtimestamp(t0).date()
    for x in mon: x['_m'] = (datetime.datetime.combine(day, datetime.time.fromisoformat(x['ts'])).timestamp() - t0) // 60
    out = []
    for m in sorted(lat):
        v = sorted(lat[m]); w = [x for x in mon if x['_m'] == m] or mon[-1:]
        mx = lambda c: max(float(x[c]) for x in w)
        out.append([m, vus[m], round(len(v) / 60, 1), round(v[len(v) // 2]), round(v[int(len(v) * .95)]),
                    mx('host_cpu_busy_pct') / 100, round(mx('app_cpu_pct') / 100, 2), round(mx('db_cpu_pct') / 100, 2),
                    round(mx('app_mem_mib')), round(mx('db_mem_mib'))])
    return out

def summ(p):
    d = json.load(open(p + '_summary.json'))['metrics']; g = lambda m, k: d.get(m, {}).get(k)
    return d, g

def table(ws, r0, headers, rows, fmts=None, widths=None):
    for j, h in enumerate(headers, 1):
        c = ws.cell(r0, j, h); c.font = H; c.fill = HF; c.alignment = Alignment(wrap_text=True, vertical='center'); c.border = BOX
    for i, row in enumerate(rows, r0 + 1):
        for j, v in enumerate(row, 1):
            c = ws.cell(i, j, v); c.font = B; c.border = BOX; c.alignment = Alignment(wrap_text=True, vertical='top')
            if fmts and fmts[j - 1]: c.number_format = fmts[j - 1]
    if widths:
        for j, w in enumerate(widths, 1): ws.column_dimensions[chr(64 + j)].width = w
    return r0 + len(rows)

wb = Workbook()

# ---------- 1. Summary ----------
ws = wb.active; ws.title = 'Summary'
ws['A1'] = 'Moodle load test (1000 students, CPU only) & .ipynb storage sizing'; ws['A1'].font = T
ws['A2'] = 'Date 2026-09-29 · Moodle 5.0.1 (Bitnami Docker) · k6 v0.54.0 · server vocab-server3'; ws['A2'].font = B
r = table(ws, 4, ['Question', 'Answer', 'Evidence'], [
    ['Can 1000 students use it at the same time (CPU only)?', 'YES — after Apache tuning', 'Test C: page p95 144 ms, 0 errors in 113,512 requests, host CPU peak 16.5 %'],
    ['Can 1000 students log in within 1 minute and all submit?', 'YES', 'Test D: login p95 93 ms, submit p95 75 ms, 1000/1000 submissions stored'],
    ['What failed with the default config?', 'Pages took 5–10 s from ~750 students', 'Test B: Apache had only 250 workers, each held 5 s by idle keep-alive connections (CPU idle at ~7 %)'],
    ['Resources used at 1000 students', '~4.5 CPU threads, ~1.7 GB RAM', 'Moodle 3.6 cores + MariaDB 0.9 cores; see sheet "Capacity"'],
    ['Storage per notebook submission', '≈ 55 KB', '49 KB file on disk + ~6 KB DB rows; see sheet "Storage"'],
    ['Storage for 1000 students, 1 assignment', '≈ 55 MB', 'Measured: filedir +48 MB, DB tables +6 MB'],
    ['GPU used?', 'No', 'Moodle containers run with runc, no GPU devices attached'],
], widths=[48, 40, 90])

ws.cell(r + 2, 1, 'Notes (setup reference)').font = BB
r = table(ws, r + 3, ['Item', 'Value', ''], [
    ['Moodle download', 'https://download.moodle.org/', ''],
    ['Installation guide', 'https://docs.moodle.org/502/en/Installing_Moodle', ''],
    ['Docker compose reference', 'https://github.com/bitnami/containers/blob/main/bitnami/moodle/docker-compose.yml', ''],
    ['Admin credentials', 'admin / AdminPassword123!', ''],
    ['Load-test students', 'loadtest0001 … loadtest1000 / LoadTest#2026, course id 2', ''],
])
ws.cell(r + 2, 1, 'Server specs').font = BB
r = table(ws, r + 3, ['Component', 'Original notes', 'Actual test server (vocab-server3)'], [
    ['OS', 'Ubuntu 24.04.3 LTS, 64-bit', 'Ubuntu, kernel 6.8.0-138'],
    ['CPU', 'Intel Core i7-13700', 'Intel Core i7-14700 (8 P-cores + 12 E-cores)'],
    ['CPU capacity', '16 cores / 24 logical CPUs, 5.2 GHz', '20 cores / 28 logical CPUs, 5.3 GHz'],
    ['RAM', '31 GB', '62 GB (~32 GB free; shared with other services)'],
    ['Primary disk', '~477 GB NVMe, ~247 GB free', '456 GB NVMe, 133 GB free (Moodle Docker volumes live here)'],
    ['Additional disk', '~931 GB', '1.8 TB, 285 GB free (not used by Moodle)'],
    ['Swap', '2 GB', '2 GB'],
    ['GPU', '—', 'RTX 4000 Ada 20 GB — not used by Moodle'],
])
ws.cell(r + 2, 1, 'Moodle hardware requirements vs measured').font = BB
table(ws, r + 3, ['Component', 'Minimum', 'Recommended / practical', 'Measured at 1000 active students'], [
    ['Disk space', '200 MB for Moodle code', '5 GB or more, depending on course content and uploaded files', 'Code 398 MB · DB ~300 MB · ~55 KB per notebook submission'],
    ['Processor', '1 GHz', '2 GHz dual-core or higher', '~4.5 CPU threads peak (~16 % of this CPU)'],
    ['Memory (RAM)', '512 MB', '1 GB or more; 8 GB+ for larger production environments', '~1.7 GB peak (Apache+PHP 1.27 GB, MariaDB 0.43 GB)'],
    ['Web & Database Servers', 'Can run on the same server', 'For larger deployments, consider separate web front-end and database servers', 'Same server was fine at 1000 (DB < 1 core)'],
    ['Additional Storage', 'Depends on content', 'Course files, videos, backups, logs, and user uploads', 'See sheet "Storage"'],
])
ws.column_dimensions['D'].width = 55

# ---------- 2. Load tests ----------
ws = wb.create_sheet('Load Tests')
rows = []
meta = [('A_baseline', 'A · default config', '50 → 300', 'Browse, 5–15 s think'),
        ('B_ramp1000', 'B · default config', '250 → 1000', 'Browse, 5–15 s think'),
        ('C_tuned1000', 'C · tuned config', '250 → 1000', 'Browse, 5–15 s think'),
        ('D_rush', 'D · tuned: login storm + submission rush', '1000 in 60 s', 'Login + upload notebook + submit')]
verdict = {'A_baseline': 'PASS', 'B_ramp1000': 'FAIL (slow)', 'C_tuned1000': 'PASS', 'D_rush': 'PASS'}
for k, name, users, flow in meta:
    d, g = summ('results/' + k)
    rows.append([name, users, flow, g('http_reqs', 'count'), g('http_req_failed', 'value'),
                 round(g('t_page', 'med')), round(g('t_page', 'p(95)')), round(g('t_page', 'p(99)')),
                 round(g('t_login', 'p(95)')), round(g('t_upload', 'p(95)')) if g('t_upload', 'p(95)') else '—',
                 round(g('t_submit', 'p(95)')) if g('t_submit', 'p(95)') else '—', verdict[k]])
table(ws, 1, ['Test', 'Students', 'Flow', 'HTTP requests', 'Error rate', 'Page p50 (ms)', 'Page p95 (ms)', 'Page p99 (ms)',
              'Login p95 (ms)', 'Upload p95 (ms)', 'Submit p95 (ms)', 'Verdict'], rows,
      [None, None, None, '#,##0', '0.00%', '#,##0', '#,##0', '#,##0', '#,##0', '#,##0', '#,##0', None],
      [36, 14, 30, 13, 10, 12, 12, 12, 12, 12, 12, 12])
ws['A7'] = 'Pass criteria: error rate < 1 % and page p95 < 3,000 ms. Every response checked for HTTP 200, no Moodle error page, no redirect to login.'; ws['A7'].font = B
ws['A8'] = 'Load generator pinned to 4 CPU cores (taskset -c 24-27). Each virtual user = one real student account. Every page also sends the navbar AJAX polls.'; ws['A8'].font = B

# ---------- 3/4. Per-minute sheets + charts ----------
hdr = ['Minute', 'Active students', 'Pages/s', 'Page p50 (ms)', 'Page p95 (ms)', 'Host CPU busy', 'Moodle CPU (cores)', 'MariaDB CPU (cores)', 'Moodle RAM (MiB)', 'MariaDB RAM (MiB)']
fm = [None, '#,##0', '0.0', '#,##0', '#,##0', '0.0%', '0.00', '0.00', '#,##0', '#,##0']
for key, title in [('B_ramp1000', 'B Default per-minute'), ('C_tuned1000', 'C Tuned per-minute')]:
    ws = wb.create_sheet(title); data = per_minute('results/' + key)
    n = table(ws, 1, hdr, data, fm, [9, 12, 10, 12, 12, 12, 13, 13, 13, 13])
    ch = LineChart(); ch.title = f'{title}: page p95 latency vs students'; ch.y_axis.title = 'p95 (ms)'; ch.x_axis.title = 'Minute'
    ch.add_data(Reference(ws, min_col=5, min_row=1, max_row=n), titles_from_data=True)
    ch.set_categories(Reference(ws, min_col=1, min_row=2, max_row=n)); ch.height, ch.width = 8, 18
    ws.add_chart(ch, f'L2')
    ch2 = LineChart(); ch2.title = 'Active students'; ch2.add_data(Reference(ws, min_col=2, min_row=1, max_row=n), titles_from_data=True)
    ch2.set_categories(Reference(ws, min_col=1, min_row=2, max_row=n)); ch2.height, ch2.width = 8, 18
    ws.add_chart(ch2, 'L19')

# ---------- 5. Storage ----------
ws = wb.create_sheet('Storage')
ws['A1'] = 'Storage per .ipynb submission (reference: Python_Basics_Assignment_ANSWER_KEY.ipynb)'; ws['A1'].font = T
table(ws, 3, ['Item', 'Value', 'Unit', 'Note'], [
    ['Reference notebook size', 42552, 'bytes', '65 cells (33 markdown, 32 code), no images; gzip 8,145 bytes'],
    ['Avg uploaded copy (unique per student)', 42897, 'bytes', 'student name + random value added to metadata so Moodle cannot dedupe'],
    ['File on disk incl. 4 KB blocks + hash dirs', 49000, 'bytes', 'filedir grew 0.2 MB → 49.2 MB for 1000 files (du -sk)'],
    ['Draft copy', 0, 'bytes', 'shares the same content-hash blob (verified 0 unshared drafts)'],
    ['DB mdl_files (4 rows × 436 B)', 1744, 'bytes', 'file + folder rows for draft and submission'],
    ['DB submission + grade rows', 800, 'bytes', 'mdl_assign_submission, mdl_grade_grades'],
    ['DB event log (~14 rows × 241 B)', 3374, 'bytes', 'login, views, upload, submit'],
    ['TOTAL per submission', '=B6+B7+B8+B9+B10', 'bytes', 'formula'],
], ['', '#,##0', None, None], [46, 14, 10, 80])
for rr in range(4, 11): ws.cell(rr, 2).font = INP
ws['A13'] = 'Projection (edit yellow cells)'; ws['A13'].font = BB
table(ws, 14, ['Input', 'Value', 'Unit', 'Note'], [
    ['Students', 1000, 'students', 'input'],
    ['Notebook assignments per term', 20, 'assignments', 'input'],
    ['Avg notebook size', 42897, 'bytes', 'input — measured copy size; use ~500,000 for notebooks with plots'],
    ['DB overhead per submission', '=B7+B8+B9+B10', 'bytes', 'from measured table above'],
    ['Disk overhead factor (blocks + dirs)', '=B6/B5', 'x', 'measured'],
], ['', '#,##0', None, None])
for rr in (15, 16, 17):
    ws.cell(rr, 2).font = INP; ws.cell(rr, 2).fill = YEL
table(ws, 21, ['Result', 'Value', 'Unit', 'Formula'], [
    ['Per submission', '=(B17*B19+B18)/1024', 'KB', '(size × overhead + DB) / 1024'],
    ['1000 students × 1 assignment', '=B15*(B17*B19+B18)/1024^2', 'MB', ''],
    ['All students × all assignments (term)', '=B15*B16*(B17*B19+B18)/1024^3', 'GB', ''],
    ['+ one course backup kept (≈ 30 % of files, zip)', '=B24*0.3', 'GB', 'assumption: notebooks compress ~3–5× in .mbz'],
], ['', '#,##0.00', None, None])
ws['A27'] = 'Scenario comparison (1000 students)'; ws['A27'].font = BB
table(ws, 28, ['Scenario', 'Notebook size (bytes)', '1 assignment (MB)', '10 assignments (GB)', '20 assignments (GB)'], [
    ['This notebook (measured)', 42897, '=1000*(B29*$B$19+$B$18)/1024^2', '=10*C29/1024', '=20*C29/1024'],
    ['Notebook with a few plots (estimate)', 500000, '=1000*(B30*$B$19+$B$18)/1024^2', '=10*C30/1024', '=20*C30/1024'],
    ['Heavy notebook / data (estimate)', 2000000, '=1000*(B31*$B$19+$B$18)/1024^2', '=10*C31/1024', '=20*C31/1024'],
], ['', '#,##0', '#,##0.0', '#,##0.00', '#,##0.00'])
for rr in (30, 31): ws.cell(rr, 2).font = INP
ws['A33'] = 'Other growth: event log ~240 B per page view (≈0.9 GB/semester for 1000 students × 30 views/day × 120 days); sessions & drafts are temporary (cron cleans).'; ws['A33'].font = B
ws['A34'] = 'Docker volumes are on the root NVMe (133 GB free).'; ws['A34'].font = B

# ---------- 6. Apache fix ----------
ws = wb.create_sheet('Apache Fix')
table(ws, 1, ['Setting', 'File / place', 'Default', 'Tuned', 'Why'], [
    ['MaxRequestWorkers / ServerLimit', 'apache/conf/extra/httpd-mpm.conf (prefork)', 250, 1000, 'prefork = 1 process per open connection; 1000 students need ~1000 connections'],
    ['StartServers / Min / MaxSpareServers', 'same', '5 / 5 / 10', '50 / 50 / 150', 'avoid slow process spawning during bursts'],
    ['MaxConnectionsPerChild', 'same', 0, 10000, 'recycle processes to cap PHP memory leaks'],
    ['KeepAliveTimeout', 'apache/conf/extra/httpd-default.conf', '5 s', '2 s', 'idle connections release their worker sooner'],
    ['max_connections', 'MariaDB (SET GLOBAL)', 151, 1100, 'each busy Apache/PHP process can open a DB connection'],
    ['innodb_buffer_pool_size', 'MariaDB (SET GLOBAL)', '128 MB', '2 GB', 'keep DB in RAM as data grows'],
    ['pm.max_children (PHP-FPM)', 'php-fpm.d/www.conf', 5, 'unchanged', 'NOT used — this image runs PHP inside Apache (mod_php)'],
], None, [36, 42, 12, 14, 70])
ws['A10'] = 'Status: applied live. Apache files survive docker restart but not container re-create; MariaDB values reset on DB restart. Persist by mounting loadtest/tuning/* in docker-compose.yaml (see report).'; ws['A10'].font = BB

# ---------- 7. Capacity ----------
ws = wb.create_sheet('Capacity')
ws['A1'] = 'How many simultaneous students can this CPU handle? (estimate from Test C, edit yellow cells)'; ws['A1'].font = T
table(ws, 3, ['Input', 'Value', 'Note'], [
    ['CPU threads used at 1000 students', 4.52, 'measured Test C peak: Moodle 3.63 + MariaDB 0.89'],
    ['Logical threads available', 24, 'user spec (i7-13700); actual test server has 28'],
    ['Hyper-threading efficiency', 0.7, 'assumption: 2 threads on one core ≈ 1.3–1.4 cores of work, and E-cores are slower'],
    ['Headroom kept free', 0.3, 'for spikes, cron, backups, other services on the box'],
    ['RAM per 1000 students (GB)', 1.7, 'measured Test C peak'],
], [None, '0.00', None], [44, 14, 80])
for rr in range(4, 9): ws.cell(rr, 2).font = INP; ws.cell(rr, 2).fill = YEL
ws['B6'].number_format = '0%'; ws['B7'].number_format = '0%'
table(ws, 11, ['Result', 'Students', 'Formula'], [
    ['Linear (ignores hyper-threading)', '=ROUND(B5*(1-B7)/B4*1000,-2)', 'threads × (1 − headroom) ÷ threads per 1000'],
    ['Conservative (with HT efficiency)', '=ROUND(B5*B6*(1-B7)/B4*1000,-2)', 'threads × HT eff × (1 − headroom) ÷ threads per 1000'],
    ['RAM needed at conservative figure (GB)', '=B13/1000*B8', ''],
], [None, '#,##0', None])
ws['A16'] = 'Beyond ~1000 students the Apache prefork design (1 process per connection) becomes the limit before the CPU: switch to event MPM + PHP-FPM.'; ws['A16'].font = B
ws['A17'] = 'Not a measured maximum — a breaking-point test above 1000 needs more test accounts.'; ws['A17'].font = B

for s in wb.worksheets: s.sheet_view.showGridLines = True
wb.save('../docs/moodle-load-and-storage-results.xlsx')
print('saved')
