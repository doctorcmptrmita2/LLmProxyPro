-- Create indexes for performance
CREATE INDEX idx_llm_requests_project_created ON codexflow.llm_requests(project_id, created_at);
CREATE INDEX idx_llm_requests_created ON codexflow.llm_requests(created_at);
CREATE UNIQUE INDEX idx_usage_daily_aggregates_project_date ON codexflow.usage_daily_aggregates(project_id, date);
CREATE INDEX idx_project_api_keys_project_revoked ON codexflow.project_api_keys(project_id, revoked_at);

-- Set character set
ALTER DATABASE codexflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
