
  [30;42;1m PASS [39;49;22m[39m Tests\Unit\ExampleTest[39m
  [32;1m✓[39;22m[90m [39m[90mthat true is true[39m[90m                                                                                                                                           [39m [90m0.01s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\AbsensiFlowTest[39m
  [32;1m✓[39;22m[90m [39m[90mlogin returns token and roles[39m[90m                                                                                                                               [39m [90m2.14s[39m  
  [32;1m✓[39;22m[90m [39m[90minactive user cannot login[39m[90m                                                                                                                                  [39m [90m0.22s[39m  
  [32;1m✓[39;22m[90m [39m[90mtrainer only sees assigned schools[39m[90m                                                                                                                          [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mtrainer can add student only in own school[39m[90m                                                                                                                  [39m [90m0.29s[39m  
  [32;1m✓[39;22m[90m [39m[90mtrainer cannot create session but management can[39m[90m                                                                                                            [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mstudent attendance sync and lock[39m[90m                                                                                                                            [39m [90m0.26s[39m  
  [32;1m✓[39;22m[90m [39m[90mtrainer online checkin without gps[39m[90m                                                                                                                          [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90monly management can manage users[39m[90m                                                                                                                            [39m [90m0.28s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\Api\DeveloperDashboardTest[39m
  [32;1m✓[39;22m[90m [39m[90mdeveloper gets developer payload without management totals[39m[90m                                                                                                  [39m [90m0.35s[39m  
  [32;1m✓[39;22m[90m [39m[90mdashboard lists only ongoing projects of own team[39m[90m                                                                                                           [39m [90m0.29s[39m  
  [32;1m✓[39;22m[90m [39m[90mdashboard counts tasks by status and workload per assignee[39m[90m                                                                                                  [39m [90m0.30s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper without team gets empty dashboard[39m[90m                                                                                                                 [39m [90m0.27s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\Api\ProjectTest[39m
  [32;1m✓[39;22m[90m [39m[90mdeveloper index lists only own team projects[39m[90m                                                                                                                [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper gets 404 for other team project[39m[90m                                                                                                                   [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement sees all projects and can filter by team[39m[90m                                                                                                         [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement cannot write projects[39m[90m                                                                                                                            [39m [90m0.28s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #0[39m[90m                                                                                                                  [39m [90m0.40s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #1[39m[90m                                                                                                                  [39m [90m0.30s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #2[39m[90m                                                                                                                  [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can create project in own team with default status[39m[90m                                                                                                [39m [90m0.26s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper cannot create project in other team[39m[90m                                                                                                               [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can update own project but gets 404 on other team[39m[90m                                                                                                 [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can delete own project but gets 404 on other team[39m[90m                                                                                                 [39m [90m0.47s[39m  
  [32;1m✓[39;22m[90m [39m[90mupdate project keeps due date after start date[39m[90m                                                                                                              [39m [90m0.33s[39m  
  [32;1m✓[39;22m[90m [39m[90mproject with tasks cannot be deleted[39m[90m                                                                                                                        [39m [90m0.31s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate project validation with data set "name wajib"[39m[90m                                                                                                        [39m [90m0.34s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate project validation with data set "status di luar enum"[39m[90m                                                                                               [39m [90m0.33s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate project validation with data set "start date wajib"[39m[90m                                                                                                  [39m [90m0.36s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate project validation with data set "due date wajib"[39m[90m                                                                                                    [39m [90m0.31s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate project validation with data set "due date sebelum start date"[39m[90m                                                                                       [39m [90m0.27s[39m  

  [39;41;1m FAIL [39;49;22m[39m Tests\Feature\Api\TaskTest[39m
  [32;1m✓[39;22m[90m [39m[90mdeveloper index lists only own team tasks and filters by project[39m[90m                                                                                            [39m [90m0.39s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper gets 404 for other team task[39m[90m                                                                                                                      [39m [90m0.31s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement sees all tasks[39m[90m                                                                                                                                   [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement cannot write tasks[39m[90m                                                                                                                               [39m [90m0.58s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #0[39m[90m                                                                                                                  [39m [90m0.55s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #1[39m[90m                                                                                                                  [39m [90m0.29s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #2[39m[90m                                                                                                                  [39m [90m0.30s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can create task with default status and progress[39m[90m                                                                                                  [39m [90m0.26s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper cannot create task in other team project[39m[90m                                                                                                          [39m [90m0.30s[39m  
  [32;1m✓[39;22m[90m [39m[90massignee must be member of project team[39m[90m                                                                                                                     [39m [90m0.65s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can update status progress and reassign to team member[39m[90m                                                                                            [39m [90m0.44s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper gets 404 on update and delete for other team task[39m[90m                                                                                                 [39m [90m0.26s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can delete own task[39m[90m                                                                                                                               [39m [90m0.24s[39m  
  [32;1m✓[39;22m[90m [39m[90mtask includes assignee name for the board[39m[90m                                                                                                                   [39m [90m0.28s[39m  
  [32;1m✓[39;22m[90m [39m[90mindex honours per page with a cap of 100[39m[90m                                                                                                                    [39m [90m0.52s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can attach github url when creating task[39m[90m                                                                                                          [39m [90m0.35s[39m  
  [32;1m✓[39;22m[90m [39m[90mgithub url must be an https github link with data set "domain lain"[39m[90m                                                                                         [39m [90m0.36s[39m  
  [32;1m✓[39;22m[90m [39m[90mgithub url must be an https github link with data set "skema javascript"[39m[90m                                                                                    [39m [90m0.35s[39m  
  [32;1m✓[39;22m[90m [39m[90mgithub url must be an https github link with data set "http bukan https"[39m[90m                                                                                    [39m [90m0.35s[39m  
  [32;1m✓[39;22m[90m [39m[90mgithub url can be cleared on update[39m[90m                                                                                                                         [39m [90m0.32s[39m  
  [32;1m✓[39;22m[90m [39m[90mpriority defaults to medium and can be set on create[39m[90m                                                                                                        [39m [90m0.35s[39m  
  [32;1m✓[39;22m[90m [39m[90mpriority can be changed on update[39m[90m                                                                                                                           [39m [90m0.40s[39m  
  [31;1m⨯[39;22m[90m [39m[90mindex can filter by priority[39m[90m                                                                                                                                [39m [90m0.26s[39m  
  [31;1m⨯[39;22m[90m [39m[90mindex can filter by due date range across projects[39m[90m                                                                                                          [39m [90m0.25s[39m  
  [31;1m⨯[39;22m[90m [39m[90mindex filters validate their input with data set "due from bukan tanggal"[39m[90m                                                                                   [39m [90m0.27s[39m  
  [31;1m⨯[39;22m[90m [39m[90mindex filters validate their input with data set "due to sebelum due from"[39m[90m                                                                                  [39m [90m0.24s[39m  
  [31;1m⨯[39;22m[90m [39m[90mindex filters validate their input with data set "priority di luar enum"[39m[90m                                                                                    [39m [90m0.24s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "title wajib"[39m[90m                                                                                                          [39m [90m0.24s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "assignee id wajib"[39m[90m                                                                                                    [39m [90m0.33s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "due date wajib"[39m[90m                                                                                                       [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "status di luar enum"[39m[90m                                                                                                  [39m [90m0.45s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "progress di atas 100"[39m[90m                                                                                                 [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "progress di bawah 0"[39m[90m                                                                                                  [39m [90m0.32s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate task validation with data set "priority di luar enum"[39m[90m                                                                                                [39m [90m0.29s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\Api\TaskUpdateTest[39m
  [32;1m✓[39;22m[90m [39m[90mchanging status and progress writes log entries[39m[90m                                                                                                             [39m [90m0.40s[39m  
  [32;1m✓[39;22m[90m [39m[90mreassigning a task logs old and new assignee names[39m[90m                                                                                                          [39m [90m0.47s[39m  
  [32;1m✓[39;22m[90m [39m[90mupdate without tracked change writes no log[39m[90m                                                                                                                 [39m [90m0.30s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper lists log newest first with author name[39m[90m                                                                                                           [39m [90m0.39s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper gets 404 for other team task log[39m[90m                                                                                                                  [39m [90m0.28s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement can read but not comment[39m[90m                                                                                                                         [39m [90m0.28s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #0[39m[90m                                                                                                                  [39m [90m0.23s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #1[39m[90m                                                                                                                  [39m [90m0.23s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #2[39m[90m                                                                                                                  [39m [90m0.47s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can post a comment[39m[90m                                                                                                                                [39m [90m0.36s[39m  
  [32;1m✓[39;22m[90m [39m[90mcomment validation with data set "note kosong"[39m[90m                                                                                                              [39m [90m0.33s[39m  
  [32;1m✓[39;22m[90m [39m[90mcomment validation with data set "note lebih dari 2000 karakter"[39m[90m                                                                                            [39m [90m0.45s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeleting a task deletes its log[39m[90m                                                                                                                             [39m [90m0.37s[39m  
  [32;1m✓[39;22m[90m [39m[90mlog survives deleting its author[39m[90m                                                                                                                            [39m [90m0.40s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\Api\TeamTest[39m
  [32;1m✓[39;22m[90m [39m[90mdeveloper index lists only own team[39m[90m                                                                                                                         [39m [90m0.30s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper can show own team with members[39m[90m                                                                                                                    [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper gets 404 for other team[39m[90m                                                                                                                           [39m [90m0.27s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement sees all teams[39m[90m                                                                                                                                   [39m [90m0.28s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement can create team with members[39m[90m                                                                                                                     [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement can update team and sync members[39m[90m                                                                                                                 [39m [90m0.29s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement can delete empty team[39m[90m                                                                                                                            [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mteam with projects cannot be deleted[39m[90m                                                                                                                        [39m [90m0.24s[39m  
  [32;1m✓[39;22m[90m [39m[90mdeveloper cannot write teams[39m[90m                                                                                                                                [39m [90m0.28s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #0[39m[90m                                                                                                                  [39m [90m0.23s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #1[39m[90m                                                                                                                  [39m [90m0.23s[39m  
  [32;1m✓[39;22m[90m [39m[90mother roles are forbidden with data set #2[39m[90m                                                                                                                  [39m [90m0.23s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate team validation with data set "name wajib"[39m[90m                                                                                                           [39m [90m0.25s[39m  
  [32;1m✓[39;22m[90m [39m[90mcreate team validation with data set "role in team di luar enum"[39m[90m                                                                                            [39m [90m0.24s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\ExampleTest[39m
  [32;1m✓[39;22m[90m [39m[90mthe application returns a successful response[39m[90m                                                                                                               [39m [90m0.19s[39m  

  [30;42;1m PASS [39;49;22m[39m Tests\Feature\ExportTest[39m
  [32;1m✓[39;22m[90m [39m[90mmanagement can export excel[39m[90m                                                                                                                                 [39m [90m1.01s[39m  
  [32;1m✓[39;22m[90m [39m[90mmanagement can export pdf[39m[90m                                                                                                                                   [39m [90m1.58s[39m  
  [32;1m✓[39;22m[90m [39m[90mtrainer can export own school only[39m[90m                                                                                                                          [39m [90m0.37s[39m  
  [31m────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────[39m  
  [41;1m FAILED [49;22m [1mTests\Feature\Api\TaskTest[22m [90m>[39m index can filter by priority                                                                                                    
[39;1m  Failed to assert that the response count matched the expected 1
Failed asserting that actual size 2 matches expected size 1.[39;22m

  at [32mtests\Feature\Api\TaskTest.php[39m:[32m370[39m
    [90m366[0m[90m▕ [0m[90;3m        [0m[39;1mSanctum[0m[35;1m::[0m[39;1mactingAs[0m[35;1m([0m[39;1m$dev[0m[35;1m);[0m
    [90m367[0m[90m▕ [0m
    [90m368[0m[90m▕ [0m[35;1m        [0m[39;1m$this[0m[35;1m->[0m[39;1mgetJson[0m[35;1m([0m[37m'/api/tasks?priority=high'[0m[35;1m)[0m
    [90m369[0m[90m▕ [0m[35;1m            ->[0m[39;1massertOk[0m[35;1m()[0m
[31;1m  ➜ [0m[3;1m370[0m[90m▕ [0m[35;1m            ->[0m[39;1massertJsonCount[0m[35;1m([0m[39;1m1[0m[35;1m, [0m[37m'data'[0m[35;1m)[0m
    [90m371[0m[90m▕ [0m[35;1m            ->[0m[39;1massertJsonPath[0m[35;1m([0m[37m'data.0.id'[0m[35;1m, [0m[39;1m$high[0m[35;1m->[0m[39;1mid[0m[35;1m);[0m
    [90m372[0m[90m▕ [0m[35;1m    }[0m
    [90m373[0m[90m▕ [0m
    [90m374[0m[90m▕ [0m[35;1m    [0m[90;3m// ---- Rentang tenggat lintas proyek (untuk kalender) ----[0m

  [31m────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────[39m  
  [41;1m FAILED [49;22m [1mTests\Feature\Api\TaskTest[22m [90m>[39m index can filter by due date range across projects                                                                              
[39;1m  Failed to assert that the response count matched the expected 2
Failed asserting that actual size 4 matches expected size 2.[39;22m

  at [32mtests\Feature\Api\TaskTest.php[39m:[32m390[39m
    [90m386[0m[90m▕ [0m[90;3m        [0m[39;1mSanctum[0m[35;1m::[0m[39;1mactingAs[0m[35;1m([0m[39;1m$dev[0m[35;1m);[0m
    [90m387[0m[90m▕ [0m
    [90m388[0m[90m▕ [0m[35;1m        [0m[39;1m$ids [0m[35;1m= [0m[39;1mcollect[0m[35;1m([0m[39;1m$this[0m[35;1m->[0m[39;1mgetJson[0m[35;1m([0m[37m'/api/tasks?due_from=2026-11-01&due_to=2026-11-30'[0m[35;1m)[0m
    [90m389[0m[90m▕ [0m[35;1m            ->[0m[39;1massertOk[0m[35;1m()[0m
[31;1m  ➜ [0m[3;1m390[0m[90m▕ [0m[35;1m            ->[0m[39;1massertJsonCount[0m[35;1m([0m[39;1m2[0m[35;1m, [0m[37m'data'[0m[35;1m)[0m
    [90m391[0m[90m▕ [0m[35;1m            ->[0m[39;1mjson[0m[35;1m([0m[37m'data'[0m[35;1m))->[0m[39;1mpluck[0m[35;1m([0m[37m'id'[0m[35;1m)->[0m[39;1msort[0m[35;1m()->[0m[39;1mvalues[0m[35;1m()->[0m[39;1mall[0m[35;1m();[0m
    [90m392[0m[90m▕ [0m
    [90m393[0m[90m▕ [0m[35;1m        [0m[90;3m// Batas bawah dan atas sama-sama termasuk.[0m
    [90m394[0m[90m▕ [0m[90;3m        [0m[39;1m$this[0m[35;1m->[0m[39;1massertSame[0m[35;1m([0m[39;1mcollect[0m[35;1m([[0m[39;1m$lower[0m[35;1m->[0m[39;1mid[0m[35;1m, [0m[39;1m$upper[0m[35;1m->[0m[39;1mid[0m[35;1m])->[0m[39;1msort[0m[35;1m()->[0m[39;1mvalues[0m[35;1m()->[0m[39;1mall[0m[35;1m(), [0m[39;1m$ids[0m[35;1m);[0m

  [31m────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────[39m  
  [41;1m FAILED [49;22m [1mTests\Feature\Api\TaskTest[22m [90m>[39m index filters validate their input with data set "due from bukan tanggal"                                                       
[39;1m  Expected response status code [422] but received 200.
Failed asserting that 200 is identical to 422.[39;22m

  at [32mtests\Feature\Api\TaskTest.php[39m:[32m405[39m
    [90m401[0m[90m▕ [0m
    [90m402[0m[90m▕ [0m[35;1m        [0m[39;1mSanctum[0m[35;1m::[0m[39;1mactingAs[0m[35;1m([0m[39;1m$dev[0m[35;1m);[0m
    [90m403[0m[90m▕ [0m
    [90m404[0m[90m▕ [0m[35;1m        [0m[39;1m$this[0m[35;1m->[0m[39;1mgetJson[0m[35;1m([0m[37m"/api/tasks?[0m[35;1m{[0m[39;1m$query[0m[35;1m}[0m[37m"[0m[35;1m)[0m
[31;1m  ➜ [0m[3;1m405[0m[90m▕ [0m[35;1m            ->[0m[39;1massertUnprocessable[0m[35;1m()[0m
    [90m406[0m[90m▕ [0m[35;1m            ->[0m[39;1massertJsonValidationErrors[0m[35;1m([0m[39;1m$errorKey[0m[35;1m);[0m
    [90m407[0m[90m▕ [0m[35;1m    }[0m
    [90m408[0m[90m▕ [0m
    [90m409[0m[90m▕ [0m[35;1m    public static function [0m[39;1minvalidIndexFilters[0m[35;1m(): array[0m

  [31m────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────[39m  
  [41;1m FAILED [49;22m [1mTests\Feature\Api\TaskTest[22m [90m>[39m index filters validate their input with data set "due to sebelum due from"                                                      
[39;1m  Expected response status code [422] but received 200.
Failed asserting that 200 is identical to 422.[39;22m

  at [32mtests\Feature\Api\TaskTest.php[39m:[32m405[39m
    [90m401[0m[90m▕ [0m
    [90m402[0m[90m▕ [0m[35;1m        [0m[39;1mSanctum[0m[35;1m::[0m[39;1mactingAs[0m[35;1m([0m[39;1m$dev[0m[35;1m);[0m
    [90m403[0m[90m▕ [0m
    [90m404[0m[90m▕ [0m[35;1m        [0m[39;1m$this[0m[35;1m->[0m[39;1mgetJson[0m[35;1m([0m[37m"/api/tasks?[0m[35;1m{[0m[39;1m$query[0m[35;1m}[0m[37m"[0m[35;1m)[0m
[31;1m  ➜ [0m[3;1m405[0m[90m▕ [0m[35;1m            ->[0m[39;1massertUnprocessable[0m[35;1m()[0m
    [90m406[0m[90m▕ [0m[35;1m            ->[0m[39;1massertJsonValidationErrors[0m[35;1m([0m[39;1m$errorKey[0m[35;1m);[0m
    [90m407[0m[90m▕ [0m[35;1m    }[0m
    [90m408[0m[90m▕ [0m
    [90m409[0m[90m▕ [0m[35;1m    public static function [0m[39;1minvalidIndexFilters[0m[35;1m(): array[0m

  [31m────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────[39m  
  [41;1m FAILED [49;22m [1mTests\Feature\Api\TaskTest[22m [90m>[39m index filters validate their input with data set "priority di luar enum"                                                        
[39;1m  Expected response status code [422] but received 200.
Failed asserting that 200 is identical to 422.[39;22m

  at [32mtests\Feature\Api\TaskTest.php[39m:[32m405[39m
    [90m401[0m[90m▕ [0m
    [90m402[0m[90m▕ [0m[35;1m        [0m[39;1mSanctum[0m[35;1m::[0m[39;1mactingAs[0m[35;1m([0m[39;1m$dev[0m[35;1m);[0m
    [90m403[0m[90m▕ [0m
    [90m404[0m[90m▕ [0m[35;1m        [0m[39;1m$this[0m[35;1m->[0m[39;1mgetJson[0m[35;1m([0m[37m"/api/tasks?[0m[35;1m{[0m[39;1m$query[0m[35;1m}[0m[37m"[0m[35;1m)[0m
[31;1m  ➜ [0m[3;1m405[0m[90m▕ [0m[35;1m            ->[0m[39;1massertUnprocessable[0m[35;1m()[0m
    [90m406[0m[90m▕ [0m[35;1m            ->[0m[39;1massertJsonValidationErrors[0m[35;1m([0m[39;1m$errorKey[0m[35;1m);[0m
    [90m407[0m[90m▕ [0m[35;1m    }[0m
    [90m408[0m[90m▕ [0m
    [90m409[0m[90m▕ [0m[35;1m    public static function [0m[39;1minvalidIndexFilters[0m[35;1m(): array[0m


  [90mTests:[39m    [31;1m5 failed[39;22m[90m,[39m[39m [39m[32;1m92 passed[39;22m[90m (346 assertions)[39m
  [90mDuration:[39m [39m35.60s[39m

