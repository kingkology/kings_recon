# Processing Architecture Comparison

## Before: Individual Job Processing
```
File Upload: 100 IPs
        │
        ├─ IpScan 1 (pending)
        ├─ IpScan 2 (pending)
        ├─ IpScan 3 (pending)
        ├─ ...
        └─ IpScan 100 (pending)
        
Queued Jobs:
├─ ProcessIpScan Job #1  (Scan IP 1)
├─ ProcessIpScan Job #2  (Scan IP 2)
├─ ProcessIpScan Job #3  (Scan IP 3)
├─ ...
└─ ProcessIpScan Job #100  (Scan IP 100)

Scheduler (Every 1 minute):
├─ Min 1: ProcessIpScan Job #1  → IP 1 scanned
├─ Min 2: ProcessIpScan Job #2  → IP 2 scanned
├─ Min 3: ProcessIpScan Job #3  → IP 3 scanned
├─ ...
└─ Min 100: ProcessIpScan Job #100  → IP 100 scanned

Total Time: ~100 minutes
```

## After: Batch Processing
```
File Upload: 100 IPs
        │
        ├─ IpScan 1 (pending)
        ├─ IpScan 2 (pending)
        ├─ IpScan 3 (pending)
        ├─ ...
        └─ IpScan 100 (pending)
        
Queued Jobs:
├─ ProcessIpScanBatch Job #1  (Batch ID: abc123)
├─ ProcessIpScanBatch Job #2  (Batch ID: abc123)  [auto-dispatched]
├─ ProcessIpScanBatch Job #3  (Batch ID: abc123)  [auto-dispatched]
├─ ...
└─ ProcessIpScanBatch Job #20 (Batch ID: abc123)  [auto-dispatched]

Scheduler (Every 1 minute):
├─ Min 1: ProcessIpScanBatch #1-5 → IPs 1-5 scanned
│         ProcessIpScanBatch #6-10 → Auto-dispatches next batch
│
├─ Min 2: ProcessIpScanBatch #11-15 → IPs 6-10 scanned
│         ProcessIpScanBatch #16-20 → Auto-dispatches next batch
│
├─ Min 3: ProcessIpScanBatch #21-25 → IPs 11-15 scanned
│         ProcessIpScanBatch #26-30 → Auto-dispatches next batch
│
├─ Min 4: ProcessIpScanBatch #31-35 → IPs 16-20 scanned
│         ProcessIpScanBatch #36-40 → Auto-dispatches next batch
│
└─ Min 5: ProcessIpScanBatch #41-45 → IPs 21-25 scanned
          [No more IPs pending]

Total Time: ~5 minutes (with --max-jobs 5)
```

## Job Dispatch Flow

### Old System
```
User Upload
    ↓
Loop: foreach IP in file
    ├─ Insert IpScan record
    └─ Dispatch ProcessIpScan job
    ↓
Result: 100 jobs in queue
```

### New System
```
User Upload
    ↓
Loop: foreach IP in file
    └─ Insert IpScan record
    ↓
Dispatch 1 ProcessIpScanBatch job
    ↓
Batch job:
    ├─ Get 5 pending IPs
    ├─ Scan each IP
    ├─ Mark as completed
    └─ If more IPs → Dispatch next batch job
    ↓
Result: 20 jobs in queue (processed in ~20 scheduler cycles)
```

## Processing Timeline

### Small Upload (10 IPs)

**Old System:**
```
Min 1: Process IP 1
Min 2: Process IP 2
Min 3: Process IP 3
Min 4: Process IP 4
Min 5: Process IP 5
Min 6: Process IP 6
Min 7: Process IP 7
Min 8: Process IP 8
Min 9: Process IP 9
Min 10: Process IP 10
Total: 10 minutes
```

**New System:**
```
Min 1: Process IPs 1-5
Min 2: Process IPs 6-10
Total: 2 minutes (5x faster!)
```

### Large Upload (100 IPs)

**Old System:**
```
Min 1-100: Process one IP per minute
Total: 100 minutes
```

**New System (with --max-jobs 10):**
```
Min 1: Process IPs 1-5, 6-10, 11-15, 16-20, 21-25 (5 batch jobs)
Min 2: Process IPs 26-30, 31-35, 36-40, 41-45, 46-50 (5 batch jobs)
Min 3: Process IPs 51-55, 56-60, 61-65, 66-70, 71-75 (5 batch jobs)
Min 4: Process IPs 76-80, 81-85, 86-90, 91-95, 96-100 (5 batch jobs)
Total: 4 minutes (25x faster!)
```

## Resource Utilization

### Old System
```
Time: ████████████████████████████████████████ 100 units
CPU:  █ (minimal, mostly idle)
Jobs: ████████████████████████████████████████ 100 jobs
```

### New System
```
Time: ████ 4 units (25% of old)
CPU:  ██████ (better utilization)
Jobs: ████ 20 jobs (20% of old)
```

## Configuration Impact

### --max-jobs Parameter

```
--max-jobs 5:
Min 1: 5 batch jobs process
       → 5 × 5 IPs = 25 IPs done
       → Remaining batches auto-dispatch
Min 2: 5 more batch jobs process
       → 5 × 5 IPs = 25 IPs done
(Continue until all done)

--max-jobs 10:
Min 1: 10 batch jobs process
       → 10 × 5 IPs = 50 IPs done
       → Remaining batches auto-dispatch
Min 2: 10 more batch jobs process
       → 10 × 5 IPs = 50 IPs done
(Finish faster!)
```

### Batch Size Parameter

```
batchSize = 5:
Batch job #1: Scan IPs 1-5
Batch job #2: Scan IPs 6-10
(More jobs total)

batchSize = 10:
Batch job #1: Scan IPs 1-10
Batch job #2: Scan IPs 11-20
(Fewer jobs but longer execution)

batchSize = 20:
Batch job #1: Scan IPs 1-20
Batch job #2: Scan IPs 21-40
(Even fewer jobs, longest execution per job)
```

## Memory & Timeout Considerations

### Batch Size vs Time

```
batchSize = 5:
  Execution time: ~5 seconds per batch
  Memory use: Low
  Parallelism: High (more jobs can queue)
  Timeout needed: 30+ seconds (has margin)

batchSize = 10:
  Execution time: ~10 seconds per batch
  Memory use: Medium
  Parallelism: Medium
  Timeout needed: 55+ seconds (has margin)

batchSize = 20:
  Execution time: ~20 seconds per batch
  Memory use: Higher
  Parallelism: Low (fewer jobs queued)
  Timeout needed: 60+ seconds (tight!)
```

### Current Configuration

```
Batch Size: 5 IPs per batch
Max Jobs:   10 per scheduler run
Timeout:    55 seconds (safe margin)
Memory:     128MB (conservative)
Tries:      3 retries on failure

Result: Fast processing with good safety margins
```

## Summary

| Aspect | Before | After | Benefit |
|--------|--------|-------|---------|
| Jobs for 100 IPs | 100 | 20 | 5x fewer |
| Time for 100 IPs | ~100 min | ~4-5 min | 20-25x faster |
| Queue overhead | Very high | Low | Much better |
| CPU utilization | Poor | Good | Better efficiency |
| Memory per execution | ~10MB | ~50MB | Acceptable |
| Database queries | 100s | Fewer | Better performance |
| Scheduler load | High | Low | Scalable |
