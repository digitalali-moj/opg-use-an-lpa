resource "aws_cloudwatch_log_group" "application_logs" {
  name              = "${local.environment}_ecs_logs"
  retention_in_days = local.account.retention_in_days

  tags = merge(
    local.default_tags,
    {
      "Name" = "${local.environment}_ecs_logs"
    },
  )
}
