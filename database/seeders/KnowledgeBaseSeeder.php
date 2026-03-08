<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * JSON 파일 목록
     * 새로운 법령 데이터를 추가할 때는 이 배열에만 파일명을 추가하면 됩니다.
     */
    private array $dataFiles = [
        'gpsp.json',
        'privacy.json',
        'contract_standard.json',
    ];

    public function run(): void
    {
        DB::table('knowledge_base')->truncate();

        foreach ($this->dataFiles as $file) {
            $path = database_path("data/knowledge_base/{$file}");

            if (!file_exists($path)) {
                $this->command->warn("파일을 찾을 수 없습니다: {$path}");
                continue;
            }

            $data = json_decode(file_get_contents($path), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->command->error("JSON 파싱 오류: {$file}");
                continue;
            }

            $this->insertTree($data);
            $this->command->info("✅ {$file} 데이터 삽입 완료");
        }

        $total = DB::table('knowledge_base')->count();
        $this->command->info("총 {$total}개의 지식베이스 데이터가 삽입되었습니다.");
    }

    /**
     * 트리 자료구조 형태로 재귀 삽입
     * root(부모) → children(자식) 순서로 삽입하여 parent_id 참조 무결성 보장
     */
    private function insertTree(array $data, ?int $parentId = null): void
    {
        // 1. 루트(부모) 노드 삽입
        $rootId = DB::table('knowledge_base')->insertGetId([
            'parent_id'     => $parentId,
            'category'      => $data['root']['category'],
            'clause_number' => $data['root']['clause_number'],
            'content'       => $data['root']['content'],
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 2. 자식 노드 삽입
        if (!empty($data['children'])) {
            $children = array_map(function (array $child) use ($rootId): array {
                return [
                    'parent_id'     => $rootId,
                    'category'      => $child['category'],
                    'clause_number' => $child['clause_number'],
                    'content'       => $child['content'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }, $data['children']);

            // bulk insert로 성능 최적화
            DB::table('knowledge_base')->insert($children);
        }
    }
}
