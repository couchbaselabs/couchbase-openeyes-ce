# SQL to N1QL Translation Guide

This guide provides patterns for converting MySQL/MariaDB SQL queries to Couchbase N1QL (SQL++).

## Basic SELECT Queries

### MySQL
```sql
SELECT * FROM patient WHERE hos_num = '12345'
```

### N1QL
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient` 
WHERE hos_num = '12345'
```

**Key Differences**:
- Must use full keyspace: `` `bucket`.`scope`.`collection` ``
- Add `META().id` to get document key
- Document key format: `{collection}::{id}`

---

## JOINs

### MySQL - Simple JOIN
```sql
SELECT p.*, e.start_date 
FROM patient p
JOIN episode e ON e.patient_id = p.id
WHERE p.id = 123
```

### N1QL - Using Document References
```sql
SELECT p.*, e.start_date
FROM `openeyes`.`core`.`patient` p
JOIN `openeyes`.`core`.`episode` e ON e.patient_id = META(p).id
WHERE META(p).id = 'patient::123'
```

**Note**: In Couchbase, foreign keys reference document keys, so use `META(p).id` instead of `p.id`.

---

### Alternative: NEST (Couchbase-Specific)

NEST embeds joined documents as an array, avoiding multiple JOINs:

```sql
SELECT p.*, ARRAY_AGG(e) as episodes
FROM `openeyes`.`core`.`patient` p
NEST `openeyes`.`core`.`episode` e ON e.patient_id = META(p).id
WHERE META(p).id = 'patient::123'
GROUP BY META(p).id, p
```

---

## Aggregations

### MySQL
```sql
SELECT firm_id, COUNT(*) as episode_count
FROM episode
WHERE start_date >= '2024-01-01'
GROUP BY firm_id
HAVING COUNT(*) > 10
ORDER BY episode_count DESC
```

### N1QL
```sql
SELECT firm_id, COUNT(*) as episode_count
FROM `openeyes`.`core`.`episode`
WHERE start_date >= '2024-01-01'
GROUP BY firm_id
HAVING COUNT(*) > 10
ORDER BY episode_count DESC
```

**Same syntax!** Most aggregation functions work identically.

---

## Subqueries

### MySQL
```sql
SELECT * FROM patient
WHERE id IN (
    SELECT patient_id FROM episode 
    WHERE subspecialty_id = 1
)
```

### N1QL - Use SELECT RAW
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient` p
WHERE META(p).id IN (
    SELECT RAW CONCAT('patient::', e.patient_id)
    FROM `openeyes`.`core`.`episode` e
    WHERE e.subspecialty_id = '1'
)
```

**Note**: `SELECT RAW` returns values directly without wrapping in objects.

---

## Date Functions

### MySQL
```sql
SELECT * FROM patient
WHERE DATE(dob) BETWEEN '1980-01-01' AND '1989-12-31'
```

### N1QL
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient`
WHERE dob BETWEEN '1980-01-01' AND '1989-12-31'
```

### Common Date Function Conversions

| MySQL | N1QL |
|-------|------|
| `NOW()` | `NOW_STR()` |
| `CURDATE()` | `SUBSTR(NOW_STR(), 0, 10)` |
| `DATE_FORMAT(date, '%Y-%m-%d')` | `SUBSTR(date, 0, 10)` |
| `DATEDIFF(date1, date2)` | `DATE_DIFF_STR(date1, date2, "day")` |
| `YEAR(date)` | `DATE_PART_STR(date, "year")` |

---

## String Functions

### MySQL LIKE
```sql
SELECT * FROM patient WHERE last_name LIKE 'Smi%'
```

### N1QL LIKE
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient`
WHERE contact.last_name LIKE 'Smi%'
```

### Case-Insensitive Search
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient`
WHERE LOWER(contact.last_name) LIKE LOWER('smi%')
```

---

## NULL Handling

### MySQL
```sql
SELECT COALESCE(nhs_num, hos_num) as identifier FROM patient
```

### N1QL
```sql
SELECT IFNULL(nhs_num, hos_num) as identifier 
FROM `openeyes`.`core`.`patient`
```

**Note**: N1QL uses `IFNULL` instead of `COALESCE` (though both work).

---

## Array Operations (Couchbase-Specific)

### Access Embedded Array Elements

```sql
SELECT META().id AS _id, elements.VisualAcuity
FROM `openeyes`.`clinical`.`examination`
WHERE ANY reading IN elements.VisualAcuity.left_readings 
      SATISFIES reading.value < 0.5 END
```

### UNNEST - Expand Arrays to Rows

```sql
SELECT META().id AS _id, r.*
FROM `openeyes`.`clinical`.`examination` e
UNNEST e.elements.VisualAcuity.left_readings r
WHERE r.value < 0.5
```

### Array Aggregation

```sql
SELECT patient_id, ARRAY_AGG(event_id) as event_ids
FROM `openeyes`.`core`.`event`
GROUP BY patient_id
```

---

## Pagination

### MySQL
```sql
SELECT * FROM patient ORDER BY last_name LIMIT 20 OFFSET 40
```

### N1QL
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient`
ORDER BY contact.last_name
LIMIT 20 OFFSET 40
```

**Same syntax!**

---

## DISTINCT

### MySQL
```sql
SELECT DISTINCT subspecialty_id FROM episode
```

### N1QL
```sql
SELECT DISTINCT subspecialty_id 
FROM `openeyes`.`core`.`episode`
```

**Same syntax!**

---

## IN Clause

### MySQL
```sql
SELECT * FROM patient WHERE id IN (1, 2, 3)
```

### N1QL
```sql
SELECT META().id AS _id, * 
FROM `openeyes`.`core`.`patient`
WHERE META().id IN ['patient::1', 'patient::2', 'patient::3']
```

**Note**: Use array syntax `[...]` instead of parentheses, and include document key prefixes.

---

## UNION

### MySQL
```sql
SELECT id, name FROM firm
UNION
SELECT id, name FROM site
```

### N1QL
```sql
SELECT firm_id as id, name FROM `openeyes`.`core`.`firm`
UNION
SELECT site_id as id, name FROM `openeyes`.`core`.`site`
```

**Same syntax!**

---

## USE KEYS (Couchbase-Specific Optimization)

When you know the exact document keys, use `USE KEYS` for fast lookup:

```sql
SELECT META().id AS _id, *
FROM `openeyes`.`core`.`patient`
USE KEYS ['patient::123', 'patient::456']
```

This bypasses index scanning and directly fetches documents.

---

## Common Gotchas

### 1. Document Keys vs. IDs
- MySQL: `WHERE id = 123`
- N1QL: `WHERE META().id = 'patient::123'`

### 2. NULL Behavior
Couchbase distinguishes between `NULL`, `MISSING`, and empty values:
- `field IS NULL` - field exists and is null
- `field IS MISSING` - field doesn't exist in document
- `field IS NOT VALUED` - field is null or missing

### 3. Type Coercion
N1QL is stricter about types:
- `WHERE age = '30'` (string) won't match `age: 30` (number)
- Use explicit conversion: `WHERE age = TONUMBER('30')`

### 4. Array Fields
Access nested fields with dot notation:
- `contact.last_name`
- `elements.VisualAcuity.left_readings[0].value`

---

## Performance Tips

1. **Use Indexes**: Ensure proper GSI indexes exist
   ```sql
   CREATE INDEX idx_patient_hosnum ON `openeyes`.`core`.`patient`(hos_num)
   ```

2. **Avoid SELECT ***: Select only needed fields
   ```sql
   SELECT hos_num, nhs_num, dob FROM ...
   ```

3. **Use Covering Indexes**: Include all queried fields in index
   ```sql
   CREATE INDEX idx_patient_search 
   ON `openeyes`.`core`.`patient`(hos_num, nhs_num, dob)
   ```

4. **USE KEYS When Possible**: Direct key lookup is fastest

5. **Avoid Subqueries**: Use JOINs or denormalization instead

---

## Query Builder Helper

Use the N1qlQueryBuilder class for easier query construction:

```php
use OE\Database\N1qlQueryBuilder;

$builder = new N1qlQueryBuilder('openeyes');
$results = $builder
    ->from('core', 'patient')
    ->select('hos_num, nhs_num, dob')
    ->whereILike('contact.last_name', 'smi%', 'lastName')
    ->orderBy('contact.last_name')
    ->limit(20)
    ->execute();
```

---

## Resources

- [Couchbase N1QL Documentation](https://docs.couchbase.com/server/current/n1ql/n1ql-language-reference/index.html)
- [SQL++ (N1QL) vs SQL Comparison](https://docs.couchbase.com/server/current/n1ql/n1ql-language-reference/conventions.html)
- [N1QL Query Performance](https://docs.couchbase.com/server/current/n1ql/n1ql-language-reference/performance.html)
